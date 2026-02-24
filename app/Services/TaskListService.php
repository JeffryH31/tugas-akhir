<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Folder;
use App\Models\Space;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskListService
{
    /**
     * Get lists for a space (optionally filtered by folder)
     */
    public function getListsForSpace(Space $space, ?Folder $folder = null): Collection
    {
        $query = $space->lists()
            ->with(['tasks' => fn($q) => $q->with(['status', 'priority', 'assignees'])])
            ->withCount('tasks');

        if ($folder) {
            $query->where('folder_id', $folder->id);
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Get a list with tasks/subtasks grouped by status
     * Returns an associative array where key is status_id and value is array of tasks/subtasks
     */
    public function getWithTasksByStatus(TaskList $list, $taskId = null): array
    {
        // Load space with statuses first
        $list->load('space.statuses');

        // If viewing subtasks, load from parent task
        if ($taskId) {
            $parentTask = \App\Models\Task::with([
                'subtasks.status',
                'subtasks.priority',
                'subtasks.assignees',
                'subtasks.labels',
                'subtasks.timeEntries.user',
                'subtasks.comments.user', // Include comments
            ])->findOrFail($taskId);
            
            $items = $parentTask->subtasks;
        } else {
            // Load tasks for the list
            $items = $list->tasks()
                ->with([
                    'status',
                    'priority',
                    'assignees',
                    'labels',
                    'subtasks.assignees', // For subtask count & assignee aggregation
                    'comments.user', // Include comments
                ])
                ->orderBy('position')
                ->get();
        }

        $statuses = $list->space->statuses;

        $grouped = [];
        foreach ($statuses as $status) {
            // Return array of items directly, not wrapped in object
            $grouped[$status->id] = $items->where('status_id', $status->id)->values()->toArray();
        }

        return $grouped;
    }

    /**
     * Create a new list
     */
    public function create(array $data, Space $space, User $user, ?Folder $folder = null): TaskList
    {
        return DB::transaction(function () use ($data, $space, $user, $folder) {
            $list = TaskList::create([
                'space_id' => $space->id,
                'folder_id' => $folder?->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? null,
                'icon' => $data['icon'] ?? null,
                'created_by' => $user->id,
            ]);

            Activity::log($space->workspace, $user, $list, 'created', [
                'name' => $list->name,
                'space_name' => $space->name,
                'folder_name' => $folder?->name,
            ]);

            return $list;
        });
    }

    /**
     * Update a list
     */
    public function update(TaskList $list, array $data, User $user): TaskList
    {
        $changes = [];
        $oldValues = $list->only(['name', 'description', 'color']);

        $list->update([
            'name' => $data['name'] ?? $list->name,
            'description' => $data['description'] ?? $list->description,
            'color' => $data['color'] ?? $list->color,
            'icon' => $data['icon'] ?? $list->icon,
        ]);

        foreach ($oldValues as $key => $oldValue) {
            if (isset($data[$key]) && $data[$key] !== $oldValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $data[$key]];
            }
        }

        if (!empty($changes)) {
            Activity::log($list->space->workspace, $user, $list, 'updated', [
                'name' => $list->name,
            ], $changes);
        }

        return $list->fresh();
    }

    /**
     * Delete a list
     */
    public function delete(TaskList $list, User $user): void
    {
        DB::transaction(function () use ($list, $user) {
            Activity::log($list->space->workspace, $user, $list, 'deleted', [
                'name' => $list->name,
            ]);

            $list->delete();
        });
    }

    /**
     * Archive a list
     */
    public function archive(TaskList $list, User $user): TaskList
    {
        $list->archive();

        Activity::log($list->space->workspace, $user, $list, 'archived', [
            'name' => $list->name,
        ]);

        return $list->fresh();
    }

    /**
     * Unarchive a list
     */
    public function unarchive(TaskList $list, User $user): TaskList
    {
        $list->unarchive();

        Activity::log($list->space->workspace, $user, $list, 'unarchived', [
            'name' => $list->name,
        ]);

        return $list->fresh();
    }

    /**
     * Move list to folder
     */
    public function moveToFolder(TaskList $list, ?Folder $folder, User $user): TaskList
    {
        $oldFolderName = $list->folder?->name ?? 'No Folder';
        $newFolderName = $folder?->name ?? 'No Folder';

        $list->update([
            'folder_id' => $folder?->id,
            'position' => TaskList::where('space_id', $list->space_id)
                ->where('folder_id', $folder?->id)
                ->max('position') + 1,
        ]);

        Activity::log($list->space->workspace, $user, $list, 'moved', [
            'name' => $list->name,
        ], [
            'folder' => ['old' => $oldFolderName, 'new' => $newFolderName],
        ]);

        return $list->fresh();
    }

    /**
     * Reorder lists
     */
    public function reorder(array $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order as $position => $listId) {
                TaskList::where('id', $listId)->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a list with its tasks
     */
    public function duplicate(TaskList $list, User $user): TaskList
    {
        return DB::transaction(function () use ($list, $user) {
            $newList = $list->replicate();
            $newList->name = $list->name . ' (Copy)';
            $newList->slug = Str::slug($newList->name);
            $newList->position = TaskList::where('space_id', $list->space_id)
                ->where('folder_id', $list->folder_id)
                ->max('position') + 1;
            $newList->save();

            // Duplicate tasks
            foreach ($list->tasks as $task) {
                $newTask = $task->replicate();
                $newTask->task_list_id = $newList->id;
                $newTask->task_id = null; // Will be auto-generated
                $newTask->save();

                // Copy task assignees and labels
                $newTask->assignees()->sync($task->assignees->pluck('id'));
                $newTask->labels()->sync($task->labels->pluck('id'));

                // Duplicate subtasks
                foreach ($task->subtasks as $subtask) {
                    $newSubtask = $subtask->replicate();
                    $newSubtask->task_id = $newTask->id;
                    $newSubtask->subtask_id = null; // Will be auto-generated
                    $newSubtask->save();
                    
                    // Copy subtask assignees and labels
                    $newSubtask->assignees()->sync($subtask->assignees->pluck('id'));
                    $newSubtask->labels()->sync($subtask->labels->pluck('id'));
                }
            }

            Activity::log($list->space->workspace, $user, $newList, 'duplicated', [
                'name' => $newList->name,
                'original_name' => $list->name,
            ]);

            return $newList;
        });
    }
}
