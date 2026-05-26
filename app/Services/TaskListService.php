<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manage product/list lifecycle, membership, board grouping, and cloning.
 */
class TaskListService
{
    /**
     * Get lists for a space, optionally scoped to a specific folder.
     *
     * @param Space $space The parent space that owns the lists.
     * @param Folder|null $folder Optional folder filter.
     * @return Collection<int, TaskList>
     */
    public function getListsForSpace(Space $space, ?Folder $folder = null): Collection
    {
        $query = $space->lists()
            ->with(['tasks' => fn($q) => $q->with(['status', 'assignees'])])
            ->withCount('tasks');

        if ($folder) {
            $query->where('folder_id', $folder->id);
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Get board payload grouped by status for tasks or subtasks.
     *
     * When taskId is provided, returns subtasks for the parent task grouped by status.
     * Otherwise returns task-level board items grouped by status.
     *
     * @param TaskList $list The list being viewed.
     * @param int|string|null $taskId Optional parent task id for subtask mode.
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getWithTasksByStatus(TaskList $list, $taskId = null): array
    {
        // Load space with statuses first
        $list->load('space.statuses');

        $parentTask = null;

        // If viewing subtasks, load from parent task
        if ($taskId) {
            $parentTask = Task::where('task_list_id', $list->id)->with([
                'subtasks.status',
                'subtasks.assignees',
                'subtasks.labels',
                'subtasks.sprint',
                'subtasks.dependencies',
                'subtasks.dependents',
                'subtasks.timeEntries.user',
                'subtasks.comments.user', // Include comments
                'subtasks.activities' => fn($q) => $q->with('user')->latest()->limit(50),
                'subtasks.checklistItems', // For checklist UI & auto-progress
                'subtasks.children.status', // Direct children (depth+1 subtasks)
                'subtasks.children.assignees',
            ])->find($taskId);
        }

        if ($parentTask) {
            $items = $parentTask->subtasks->whereNull('parent_id');
        } else {
            // Load tasks for the list
            $items = $list->tasks()
                ->with([
                    'status',
                    'assignees',
                    'labels',
                    'subtasks.assignees', // For subtask count & assignee aggregation
                    'comments.user', // Include comments
                    'activities' => fn($q) => $q->with('user')->latest()->limit(50),
                ])
                ->orderBy('position')
                ->get();

            // Task-level assignees are derived from subtask assignees.
            $items->each(function ($task) {
                $aggregatedAssignees = $task->subtasks
                    ->flatMap(fn($subtask) => $subtask->assignees ?? collect())
                    ->unique('id')
                    ->values();

                $task->setRelation('assignees', $aggregatedAssignees);
            });
        }

        $statuses = $list->space->statuses;

        $grouped = [];
        foreach ($statuses as $status) {
            $statusItems = $items->where('status_id', $status->id);

            // Sort by due_date ascending (nearest first), nulls at the end
            $statusItems = $statusItems->sort(function ($a, $b) {
                $aDate = $a->due_date ?? null;
                $bDate = $b->due_date ?? null;

                if ($aDate === null && $bDate === null) return $a->position <=> $b->position;
                if ($aDate === null) return 1;
                if ($bDate === null) return -1;

                return $aDate <=> $bDate;
            });

            $grouped[$status->id] = $statusItems->values()->toArray();
        }

        return $grouped;
    }

    /**
     * Create a new list/product inside a space.
     *
     * @param array<string, mixed> $data Validated list payload.
     * @param Space $space Parent space.
     * @param User $user Current actor.
     * @param Folder|null $folder Optional folder destination.
     * @return TaskList
     */
    public function create(array $data, Space $space, User $user, ?Folder $folder = null): TaskList
    {
        return DB::transaction(function () use ($data, $space, $user, $folder) {
            // Backward-compat: older spaces may miss one of the default task statuses.
            $this->ensureDefaultTaskStatuses($space);

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

            $list->addMember($user, 'project_owner');

            Activity::log($space->workspace, $user, $list, 'created', [
                'name' => $list->name,
                'space_name' => $space->name,
                'folder_name' => $folder?->name,
            ]);

            return $list;
        });
    }

    /**
     * Ensure canonical task statuses exist for the space.
     *
     * @param Space $space Target space.
     * @return void
     */
    private function ensureDefaultTaskStatuses(Space $space): void
    {
        $defaults = [
            [
                'type' => 'open',
                'name' => 'Open',
                'slug' => 'open',
                'color' => '#6B7280',
                'position' => 0,
                'is_default' => true,
                'is_closed' => false,
            ],
            [
                'type' => 'in_progress',
                'name' => 'In Progress',
                'slug' => 'in-progress',
                'color' => '#3B82F6',
                'position' => 1,
                'is_default' => false,
                'is_closed' => false,
            ],
            [
                'type' => 'review',
                'name' => 'Review',
                'slug' => 'review',
                'color' => '#F59E0B',
                'position' => 2,
                'is_default' => false,
                'is_closed' => false,
            ],
            [
                'type' => 'closed',
                'name' => 'Completed',
                'slug' => 'completed',
                'color' => '#10B981',
                'position' => 3,
                'is_default' => false,
                'is_closed' => true,
            ],
        ];

        foreach ($defaults as $default) {
            $status = $space->statuses()
                ->where(fn($q) => $q->where('type', $default['type'])->orWhere('slug', $default['slug']))
                ->first();

            if ($status) {
                $updates = [];

                if ($status->type !== $default['type']) {
                    $updates['type'] = $default['type'];
                }

                if (!in_array($status->applies_to, ['tasks', 'both'], true)) {
                    $updates['applies_to'] = 'both';
                }

                if ($default['is_closed'] && !$status->is_closed) {
                    $updates['is_closed'] = true;
                }

                if ($default['is_default']) {
                    $hasDefaultTaskStatus = $space->statuses()->forTasks()->where('is_default', true)->exists();
                    if (!$hasDefaultTaskStatus && !$status->is_default) {
                        $updates['is_default'] = true;
                    }
                }

                if (!empty($updates)) {
                    $status->update($updates);
                }

                continue;
            }

            // Keep insertion in a sensible order without touching existing custom statuses more than needed.
            $space->statuses()->where('position', '>=', $default['position'])->increment('position');

            Status::create([
                'space_id' => $space->id,
                'name' => $default['name'],
                'slug' => $default['slug'],
                'color' => $default['color'],
                'type' => $default['type'],
                'applies_to' => 'both',
                'position' => $default['position'],
                'is_default' => $default['is_default'],
                'is_closed' => $default['is_closed'],
            ]);
        }
    }

    /**
     * Add a member to a list with a role.
     *
     * @param TaskList $list Target list.
     * @param User $user Member to add.
     * @param string $role Membership role.
     * @param User $addedBy Actor performing the operation.
     * @return void
     */
    public function addMember(TaskList $list, User $user, string $role, User $addedBy): void
    {
        $list->addMember($user, $role);

        Activity::log($list->space->workspace, $addedBy, $list, 'member_added', [
            'name' => $list->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Update an existing list member role.
     *
     * @param TaskList $list Target list.
     * @param User $user Member whose role is being updated.
     * @param string $role New role value.
     * @param User $updatedBy Actor performing the operation.
     * @return void
     */
    public function updateMemberRole(TaskList $list, User $user, string $role, User $updatedBy): void
    {
        $list->members()->updateExistingPivot($user->id, ['role' => $role]);

        Activity::log($list->space->workspace, $updatedBy, $list, 'member_role_updated', [
            'name' => $list->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Remove a member from a list.
     *
     * @param TaskList $list Target list.
     * @param User $user Member to remove.
     * @param User $removedBy Actor performing the operation.
     * @return void
     */
    public function removeMember(TaskList $list, User $user, User $removedBy): void
    {
        $list->members()->detach($user->id);

        Activity::log($list->space->workspace, $removedBy, $list, 'member_removed', [
            'name' => $list->name,
            'member_name' => $user->name,
        ]);
    }

    /**
     * Update list metadata.
     *
     * @param TaskList $list Target list.
     * @param array<string, mixed> $data Validated update payload.
     * @param User $user Current actor.
     * @return TaskList
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
     * Soft-delete a list.
     *
     * @param TaskList $list Target list.
     * @param User $user Current actor.
     * @return void
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
     * Move a list to another folder (or root when null).
     *
     * @param TaskList $list Target list.
     * @param Folder|null $folder Destination folder, null for root.
     * @param User $user Current actor.
     * @return TaskList
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
     * Reorder list positions within a space.
     *
     * @param Space $space Target space.
     * @param array<int, int|string> $order Ordered list ids.
     * @return void
     */
    public function reorder(Space $space, array $order): void
    {
        DB::transaction(function () use ($space, $order) {
            foreach ($order as $position => $listId) {
                TaskList::where('id', $listId)
                    ->where('space_id', $space->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a list and clone all tasks/subtasks and their assignments/labels.
     *
     * @param TaskList $list Source list.
     * @param User $user Current actor.
     * @return TaskList
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

            // Only copy the creator as project_owner, not all members
            $newList->addMember($user, 'project_owner');

            foreach ($list->tasks()->with(['labels', 'subtasks.labels'])->get() as $task) {
                $newTask = $task->replicate(['task_id']);
                $newTask->task_list_id = $newList->id;
                // Reset operational fields — this is a template copy, not a continuation
                $newTask->start_date = null;
                $newTask->due_date = null;
                $newTask->save();

                // Copy labels (structural), but NOT assignees (operational)
                $newTask->labels()->sync($task->labels->pluck('id'));

                foreach ($task->subtasks as $subtask) {
                    $newSubtask = $subtask->replicate([
                        'subtask_id',
                        'completed_at',
                        'completed_by',
                        'time_spent',
                        'sprint_id',
                    ]);
                    $newSubtask->task_id = $newTask->id;
                    // Reset operational fields
                    $newSubtask->start_date = null;
                    $newSubtask->due_date = null;
                    $newSubtask->baseline_start_date = null;
                    $newSubtask->baseline_due_date = null;
                    $newSubtask->progress = 0;
                    $newSubtask->save();

                    // Copy labels (structural), but NOT assignees (operational)
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
