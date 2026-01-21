<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\TaskList;
use App\Models\Comment;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TaskService 
 *
 * Handles all business logic related to tasks including subtasks,
 * checklists, comments, dependencies, and more.
 */
class TaskService
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    // ==========================================
    // Task CRUD Operations
    // ==========================================

    /**
     * Create a new task.
     */
    public function createTask(TaskList $list, User $creator, array $data): Task
    {
        return DB::transaction(function () use ($list, $creator, $data) {
            $maxPosition = $list->tasks()->whereNull('parent_id')->max('position') ?? -1;

            // Get default status
            $defaultStatus = $list->statuses()->where('is_default', true)->first()
                ?? $list->statuses()->orderBy('position')->first();

            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'list_id' => $list->id,
                'parent_id' => $data['parent_id'] ?? null,
                'status_id' => $data['status_id'] ?? $defaultStatus?->id,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'created_by' => $creator->id,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? 0,
                'position' => $data['position'] ?? $maxPosition + 1,
            ]);

            // Add assignee if provided (single assignee -> add to pivot)
            if (!empty($data['assignee_id'])) {
                $task->assignees()->attach($data['assignee_id']);
            }

            // Add multiple assignees if provided
            if (!empty($data['assignee_ids'])) {
                $task->assignees()->attach($data['assignee_ids']);
            }

            // Add labels if provided
            if (!empty($data['label_ids'])) {
                $task->labels()->attach($data['label_ids']);
            }

            // Add watchers if provided
            if (!empty($data['watcher_ids'])) {
                $task->watchers()->attach($data['watcher_ids']);
            }

            // Log activity
            $this->activityService->logTaskCreated($creator, $task);

            return $task->load(['assignee', 'assignees', 'labels', 'statusModel']);
        });
    }

    /**
     * Create a subtask.
     */
    public function createSubtask(Task $parentTask, User $creator, array $data): Task
    {
        $data['parent_id'] = $parentTask->id;
        $data['list_id'] = $parentTask->list_id;
        
        return $this->createTask($parentTask->list, $creator, $data);
    }

    /**
     * Update a task.
     */
    public function updateTask(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $updateData = [];

            // Basic fields
            foreach (['title', 'description', 'status', 'priority', 'start_date', 'due_date', 'estimated_hours', 'status_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle single assignee_id by syncing to pivot table
            if (array_key_exists('assignee_id', $data)) {
                if ($data['assignee_id']) {
                    $task->assignees()->syncWithoutDetaching([$data['assignee_id']]);
                }
            }

            if (!empty($updateData)) {
                $task->update($updateData);
            }

            // Update multiple assignees
            if (array_key_exists('assignee_ids', $data)) {
                $task->assignees()->sync($data['assignee_ids'] ?? []);
            }

            // Update labels
            if (array_key_exists('label_ids', $data)) {
                $task->labels()->sync($data['label_ids'] ?? []);
            }

            // Update watchers
            if (array_key_exists('watcher_ids', $data)) {
                $task->watchers()->sync($data['watcher_ids'] ?? []);
            }

            // Handle custom field values
            if (!empty($data['custom_fields'])) {
                foreach ($data['custom_fields'] as $fieldId => $value) {
                    $task->customFieldValues()->updateOrCreate(
                        ['custom_field_id' => $fieldId],
                        ['value' => $value]
                    );
                }
            }

            return $task->fresh(['assignee', 'assignees', 'labels', 'statusModel', 'subtasks']);
        });
    }

    /**
     * Delete a task (soft delete).
     */
    public function deleteTask(Task $task): bool
    {
        return DB::transaction(function () use ($task) {
            // Delete all subtasks first
            $task->subtasks()->each(fn($subtask) => $subtask->delete());
            
            // Delete related items
            $task->checklists()->delete();
            $task->comments()->delete();
            $task->timeEntries()->delete();
            $task->dependencies()->delete();
            $task->dependents()->delete();
            
            return $task->delete();
        });
    }

    // ==========================================
    // Task Status & Completion
    // ==========================================

    /**
     * Toggle task completion status.
     */
    public function toggleCompletion(Task $task, User $user): Task
    {
        if ($task->is_completed) {
            $task->markAsIncomplete();
        } else {
            $task->markAsCompleted();
            $this->activityService->logTaskCompleted($user, $task);
        }

        return $task->fresh();
    }

    /**
     * Change task status.
     */
    public function changeStatus(Task $task, int $statusId, User $user): Task
    {
        $oldStatus = $task->statusModel?->name ?? $task->status;
        
        $task->update(['status_id' => $statusId]);

        // Check if new status is closed type
        $newStatus = $task->fresh()->statusModel;
        if ($newStatus && $newStatus->isClosed() && !$task->is_completed) {
            $task->markAsCompleted();
        } elseif ($newStatus && !$newStatus->isClosed() && $task->is_completed) {
            $task->markAsIncomplete();
        }

        return $task->fresh();
    }

    /**
     * Change task priority.
     */
    public function changePriority(Task $task, string $priority): Task
    {
        $task->update(['priority' => $priority]);
        return $task->fresh();
    }

    // ==========================================
    // Task Movement & Ordering
    // ==========================================

    /**
     * Move a task to another list.
     */
    public function moveTask(Task $task, TaskList $targetList, User $user, ?int $position = null): Task
    {
        return DB::transaction(function () use ($task, $targetList, $user, $position) {
            $oldList = $task->list;

            if ($position === null) {
                $position = $targetList->tasks()->whereNull('parent_id')->max('position') + 1;
            }

            $task->update([
                'list_id' => $targetList->id,
                'position' => $position,
            ]);

            // Reorder in old list
            $this->reorderTasksInList($oldList);

            // Log activity
            $this->activityService->logTaskMoved($user, $task, $oldList->name, $targetList->name);

            return $task->fresh();
        });
    }

    /**
     * Alias for moveTask - for backward compatibility.
     */
    public function moveToList(Task $task, TaskList $targetList, User $user, ?int $position = null): Task
    {
        return $this->moveTask($task, $targetList, $user, $position);
    }

    /**
     * Reorder tasks within a list.
     */
    public function reorderTasks(TaskList $list, array $taskIds): void
    {
        DB::transaction(function () use ($list, $taskIds) {
            foreach ($taskIds as $position => $taskId) {
                Task::where('id', $taskId)
                    ->where('list_id', $list->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Reorder tasks in a list sequentially.
     */
    private function reorderTasksInList(TaskList $list): void
    {
        $tasks = $list->tasks()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get();

        foreach ($tasks as $index => $task) {
            $task->update(['position' => $index]);
        }
    }

    // ==========================================
    // Assignees
    // ==========================================

    /**
     * Assign a user to a task.
     */
    public function assignTask(Task $task, ?User $assignee, User $assigner): Task
    {
        if ($assignee) {
            if (!$task->assignees()->where('user_id', $assignee->id)->exists()) {
                $task->assignees()->attach($assignee->id);
            }
            $this->activityService->logTaskAssigned($assigner, $task, $assignee);
        }

        return $task->fresh();
    }

    /**
     * Add multiple assignees.
     */
    public function addAssignees(Task $task, array $userIds): Task
    {
        $task->assignees()->syncWithoutDetaching($userIds);
        return $task->fresh();
    }

    /**
     * Remove an assignee.
     */
    public function removeAssignee(Task $task, int $userId): Task
    {
        $task->assignees()->detach($userId);
        
        return $task->fresh();
    }

    // ==========================================
    // Checklists
    // ==========================================

    /**
     * Create a checklist in a task.
     */
    public function createChecklist(Task $task, string $name): Checklist
    {
        $position = $task->checklists()->max('position') ?? -1;

        return $task->checklists()->create([
            'name' => $name,
            'position' => $position + 1,
        ]);
    }

    /**
     * Add an item to a checklist.
     */
    public function addChecklistItem(Checklist $checklist, array $data): ChecklistItem
    {
        $position = $checklist->items()->max('position') ?? -1;

        return $checklist->items()->create([
            'name' => $data['name'],
            'assignee_id' => $data['assignee_id'] ?? null,
            'position' => $position + 1,
        ]);
    }

    /**
     * Toggle checklist item completion.
     */
    public function toggleChecklistItem(ChecklistItem $item): ChecklistItem
    {
        $item->toggleComplete();
        return $item->fresh();
    }

    // ==========================================
    // Comments
    // ==========================================

    /**
     * Add a comment to a task.
     */
    public function addComment(Task $task, User $user, string $content, ?int $parentId = null): Comment
    {
        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => $parentId,
        ]);

        // Extract mentions
        preg_match_all('/@(\w+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentionedUsers = User::whereIn('name', $matches[1])->pluck('id')->toArray();
            $comment->update(['mentions' => $mentionedUsers]);
        }

        return $comment->load('user');
    }

    /**
     * Resolve a comment.
     */
    public function resolveComment(Comment $comment): Comment
    {
        $comment->resolve();
        return $comment->fresh();
    }

    // ==========================================
    // Dependencies
    // ==========================================

    /**
     * Add a dependency (waiting on).
     */
    public function addDependency(Task $task, Task $dependsOn, string $type = 'waiting_on'): TaskDependency
    {
        return TaskDependency::create([
            'task_id' => $task->id,
            'depends_on_id' => $dependsOn->id,
            'type' => $type,
        ]);
    }

    /**
     * Remove a dependency.
     */
    public function removeDependency(Task $task, Task $dependsOn): bool
    {
        return TaskDependency::where('task_id', $task->id)
            ->where('depends_on_id', $dependsOn->id)
            ->delete() > 0;
    }

    // ==========================================
    // Watchers
    // ==========================================

    /**
     * Add a watcher to a task.
     */
    public function addWatcher(Task $task, User $user): void
    {
        if (!$task->watchers()->where('user_id', $user->id)->exists()) {
            $task->watchers()->attach($user->id);
        }
    }

    /**
     * Remove a watcher from a task.
     */
    public function removeWatcher(Task $task, User $user): void
    {
        $task->watchers()->detach($user->id);
    }

    // ==========================================
    // Time Tracking
    // ==========================================

    /**
     * Update task estimation.
     */
    public function updateEstimation(Task $task, float $hours, User $user): Task
    {
        $oldEstimate = $task->estimated_hours;

        $task->update(['estimated_hours' => $hours]);

        $this->activityService->logEstimationUpdated($user, $task, $oldEstimate, $hours);

        return $task->fresh();
    }

    // ==========================================
    // Queries
    // ==========================================

    /**
     * Get tasks assigned to a user.
     */
    public function getTasksForUser(User $user, array $filters = []): Collection
    {
        $query = Task::query()
            ->active()
            ->whereNull('parent_id')
            ->whereHas('assignees', fn($q) => $q->where('users.id', $user->id))
            ->with(['list.space', 'assignees', 'labels', 'statusModel', 'subtasks']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (($filters['due_date'] ?? null) === 'overdue') {
            $query->overdue();
        }

        if (!empty($filters['list_id'])) {
            $query->where('list_id', $filters['list_id']);
        }

        if (!empty($filters['space_id'])) {
            $query->whereHas('list', fn($q) => $q->where('space_id', $filters['space_id']));
        }

        return $query->orderBy('due_date')->orderBy('priority')->get();
    }

    /**
     * Search tasks.
     */
    public function searchTasks(string $query, ?int $workspaceId = null): Collection
    {
        return Task::query()
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->when($workspaceId, function ($q) use ($workspaceId) {
                $q->whereHas('list.space.workspace', fn($q) => $q->where('id', $workspaceId));
            })
            ->with(['list.space', 'assignee'])
            ->limit(50)
            ->get();
    }

    /**
     * Duplicate a task.
     */
    public function duplicateTask(Task $task, User $creator, ?string $newTitle = null): Task
    {
        return DB::transaction(function () use ($task, $creator, $newTitle) {
            $newTask = $task->replicate(['completed_at']);
            $newTask->title = $newTitle ?? $task->title . ' (Copy)';
            $newTask->is_completed = false;
            $newTask->created_by = $creator->id;
            $newTask->position = $task->list->tasks()->whereNull('parent_id')->max('position') + 1;
            $newTask->save();

            // Copy labels
            $newTask->labels()->attach($task->labels->pluck('id'));

            // Copy checklists
            foreach ($task->checklists as $checklist) {
                $newChecklist = $checklist->replicate();
                $newChecklist->task_id = $newTask->id;
                $newChecklist->save();

                foreach ($checklist->items as $item) {
                    $newItem = $item->replicate(['completed_at']);
                    $newItem->checklist_id = $newChecklist->id;
                    $newItem->is_completed = false;
                    $newItem->save();
                }
            }

            return $newTask->load(['assignee', 'labels', 'checklists.items']);
        });
    }
}
