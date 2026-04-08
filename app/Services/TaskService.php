<?php

namespace App\Services;

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Label;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Get tasks for a list with filtering and pagination
     */
    public function getTasksForList(
        TaskList $list,
        array $filters = [],
        ?string $sortBy = 'position',
        string $sortDirection = 'asc',
        ?int $perPage = null
    ): Collection|LengthAwarePaginator {
        $query = $list->tasks()
            ->with([
                'status',
                'assignees',
                'labels',
                'subtasks' => fn($q) => $q->with(['status', 'assignees']),
                'creator',
            ]);

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get task with all relations
     */
    public function getTaskWithRelations(Task $task): Task
    {
        return $task->load([
            'taskList.space.workspace',
            'status',
            'assignees',
            'labels',
            'subtasks' => fn($q) => $q->with(['status', 'assignees', 'dependencies', 'dependents', 'timeEntries.user'])->orderBy('position'),
            'dependencies',
            'dependents',
            'comments' => fn($q) => $q->whereNull('parent_id')->with(['user', 'replies.user'])->latest(),
            'activities' => fn($q) => $q->with('user')->latest()->limit(50),
            'attachments',
            'creator',
        ]);
    }

    /**
     * Create a new task
     */
    public function create(array $data, TaskList $list, User $user): Task
    {
        return DB::transaction(function () use ($data, $list, $user) {
            $task = Task::create([
                'task_list_id' => $list->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'priority_level' => $data['priority_level'] ?? null,
                'created_by' => $user->id,
            ]);


            if (!empty($data['assignee_ids'])) {
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $assignee = User::findOrFail($assigneeId);
                    $task->assign($assignee, $user);
                }
            }

            if (!empty($data['label_ids'])) {
                $task->labels()->sync($data['label_ids']);
            }

            Activity::log($list->space->workspace, $user, $task, 'created', [
                'name' => $task->name,
            ]);

            return $task->fresh(['status', 'assignees', 'labels']);
        });
    }

    /**
     * Update a task
     */
    public function update(Task $task, array $data, User $user): Task
    {
        return DB::transaction(function () use ($task, $data, $user) {
            $changes = [];
            // Capture before update – needed for readable activity descriptions
            $oldStatusId     = $task->status_id;
            $oldStatusName   = $task->status?->name;
            $oldPriorityEnum = $task->priority_level; // PriorityLevel enum|null

            $oldValues = $task->only(['name', 'description']);

            $task->update([
                'name'           => $data['name'] ?? $task->name,
                'description'    => $data['description'] ?? $task->description,
                'status_id'      => $data['status_id'] ?? $task->status_id,
                'priority_level' => $data['priority_level'] ?? $task->priority_level,
            ]);

            foreach ($oldValues as $key => $oldValue) {
                $newValue = $data[$key] ?? $task->$key;
                if ($newValue != $oldValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $oldAssigneeIds = $task->assignees->pluck('id')->toArray();
                $newAssigneeIds  = $data['assignee_ids'];
                $task->assignees()->sync($newAssigneeIds);

                $addedIds   = array_diff($newAssigneeIds, $oldAssigneeIds);
                $removedIds = array_diff($oldAssigneeIds, $newAssigneeIds);

                foreach ($addedIds as $id) {
                    $assignee = User::find($id);
                    if ($assignee) {
                        Activity::log($task->taskList->space->workspace, $user, $task, 'assigned', [
                            'name'          => $task->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id'   => $assignee->id,
                        ]);
                    }
                }

                foreach ($removedIds as $id) {
                    $assignee = User::find($id);
                    if ($assignee) {
                        Activity::log($task->taskList->space->workspace, $user, $task, 'unassigned', [
                            'name'          => $task->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id'   => $assignee->id,
                        ]);
                    }
                }
                // Assignee changes are logged separately above – don't add to generic $changes
            }

            // Update labels if provided
            if (isset($data['label_ids'])) {
                $task->labels()->sync($data['label_ids']);
            }

            if (!empty($changes)) {
                Activity::log($task->taskList->space->workspace, $user, $task, 'updated', [
                    'name' => $task->name,
                ], $changes);
            }

            // Log status change with readable names
            if (isset($data['status_id']) && (int) $data['status_id'] !== (int) $oldStatusId) {
                $newStatus = Status::find($data['status_id']);
                Activity::log($task->taskList->space->workspace, $user, $task, 'status_changed', [
                    'name' => $task->name,
                ], [
                    'status' => ['old' => $oldStatusName, 'new' => $newStatus?->name],
                ]);
            }

            // Log priority change with readable labels
            if (array_key_exists('priority_level', $data) && $data['priority_level'] !== $oldPriorityEnum?->value) {
                $oldLabel = $oldPriorityEnum?->label();
                $newLabel = $data['priority_level'] !== null ? PriorityLevel::from((int) $data['priority_level'])->label() : null;
                Activity::log($task->taskList->space->workspace, $user, $task, 'priority_changed', [
                    'name' => $task->name,
                ], [
                    'priority' => ['old' => $oldLabel, 'new' => $newLabel],
                ]);
            }

            return $task->fresh();
        });
    }

    /**
     * Delete a task
     */
    public function delete(Task $task, User $user): void
    {
        DB::transaction(function () use ($task, $user) {
            Activity::log($task->taskList->space->workspace, $user, $task, 'deleted', [
                'name' => $task->name,
            ]);

            $task->delete();
        });
    }



    /**
     * Change task status
     */
    public function changeStatus(Task $task, Status $status, User $user): Task
    {
        $oldStatus = $task->status;
        $task->changeStatus($status);

        Activity::log($task->taskList->space->workspace, $user, $task, 'status_changed', [
            'name' => $task->name,
        ], [
            'status' => [
                'old' => $oldStatus?->name,
                'new' => $status->name,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Change task priority
     */
    public function changePriority(Task $task, ?int $priorityLevel, User $user): Task
    {
        $oldPriority = $task->priority_level;
        $task->update(['priority_level' => $priorityLevel]);

        Activity::log($task->taskList->space->workspace, $user, $task, 'priority_changed', [
            'name' => $task->name,
        ], [
            'priority' => [
                'old' => $oldPriority?->label(),
                'new' => $priorityLevel ? PriorityLevel::from($priorityLevel)->label() : null,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Assign user to task
     */
    public function assign(Task $task, User $assignee, User $assignedBy): Task
    {
        $task->assign($assignee, $assignedBy);

        Activity::log($task->taskList->space->workspace, $assignedBy, $task, 'assigned', [
            'name' => $task->name,
            'assignee_name' => $assignee->name,
            'assignee_id' => $assignee->id,
        ]);

        return $task->fresh();
    }

    /**
     * Unassign user from task
     */
    public function unassign(Task $task, User $assignee, User $unassignedBy): Task
    {
        $task->unassign($assignee);

        Activity::log($task->taskList->space->workspace, $unassignedBy, $task, 'unassigned', [
            'name' => $task->name,
            'assignee_name' => $assignee->name,
        ]);

        return $task->fresh();
    }

    /**
     * Move task to different list
     */
    public function move(Task $task, TaskList $newList, User $user, ?int $position = null): Task
    {
        $oldList = $task->taskList;
        $task->move($newList, $position);

        Activity::log($newList->space->workspace, $user, $task, 'moved', [
            'name' => $task->name,
        ], [
            'list' => [
                'old' => $oldList->name,
                'new' => $newList->name,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Reorder tasks within a list
     */
    public function reorder(TaskList $list, array $order): void
    {
        DB::transaction(function () use ($list, $order) {
            foreach ($order as $position => $taskId) {
                Task::where('id', $taskId)
                    ->where('task_list_id', $list->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Add label to task
     */
    public function addLabel(Task $task, Label $label, User $user): Task
    {
        $task->addLabel($label);

        Activity::log($task->taskList->space->workspace, $user, $task, 'label_added', [
            'name' => $task->name,
            'label_name' => $label->name,
        ]);

        return $task->fresh();
    }

    /**
     * Remove label from task
     */
    public function removeLabel(Task $task, Label $label, User $user): Task
    {
        $task->removeLabel($label);

        Activity::log($task->taskList->space->workspace, $user, $task, 'label_removed', [
            'name' => $task->name,
            'label_name' => $label->name,
        ]);

        return $task->fresh();
    }

    /**
     * Duplicate a task
     */
    public function duplicate(Task $task, User $user): Task
    {
        return DB::transaction(function () use ($task, $user) {
            $newTask = $task->replicate(['task_id']);
            $newTask->name = $task->name . ' (Copy)';
            $newTask->position = Task::where('task_list_id', $task->task_list_id)
                ->max('position') + 1;
            $newTask->save();

            $newTask->assignees()->sync($task->assignees->pluck('id'));

            $newTask->labels()->sync($task->labels->pluck('id'));

            foreach ($task->subtasks as $subtask) {
                $newSubtask = $subtask->replicate([
                    'subtask_id',
                    'completed_at',
                    'completed_by',
                    'time_spent'
                ]);
                $newSubtask->task_id = $newTask->id;
                $newSubtask->save();

                $newSubtask->assignees()->sync($subtask->assignees->pluck('id'));
                $newSubtask->labels()->sync($subtask->labels->pluck('id'));
            }

            Activity::log($task->taskList->space->workspace, $user, $newTask, 'duplicated', [
                'name' => $newTask->name,
                'original_name' => $task->name,
            ]);

            return $newTask;
        });
    }

    /**
     * Apply filters to task query
     */
    protected function applyFilters($query, array $filters)
    {
        if (!empty($filters['status_ids'])) {
            $query->whereIn('status_id', $filters['status_ids']);
        }

        if (!empty($filters['priority_levels'])) {
            $query->whereIn('priority_level', $filters['priority_levels']);
        }

        if (!empty($filters['assignee_ids'])) {
            $query->whereHas('assignees', fn($q) => $q->whereIn('user_id', $filters['assignee_ids']));
        }

        if (!empty($filters['label_ids'])) {
            $query->whereHas('labels', fn($q) => $q->whereIn('label_id', $filters['label_ids']));
        }

        // Tasks don't have completed_at - only subtasks do
        // Filter by tasks with all subtasks completed or incomplete
        if (!empty($filters['is_completed'])) {
            $query->whereDoesntHave('subtasks', fn($q) => $q->whereNull('completed_at'));
        } elseif (isset($filters['is_completed']) && $filters['is_completed'] === false) {
            $query->whereHas('subtasks', fn($q) => $q->whereNull('completed_at'));
        }

        // Tasks don't have due dates - only subtasks do
        if (!empty($filters['is_overdue'])) {
            $query->whereHas(
                'subtasks',
                fn($q) =>
                $q->whereNull('completed_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
            );
        }

        if (!empty($filters['due_date_from'])) {
            $query->whereHas(
                'subtasks',
                fn($q) =>
                $q->where('due_date', '>=', $filters['due_date_from'])
            );
        }

        if (!empty($filters['due_date_to'])) {
            $query->whereHas(
                'subtasks',
                fn($q) =>
                $q->where('due_date', '<=', $filters['due_date_to'])
            );
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('task_id', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Get user's assigned tasks across all workspaces
     */
    public function getMyTasks(User $user, array $filters = []): Collection
    {
        $query = Task::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('assignees', fn($assignees) => $assignees->where('users.id', $user->id))
                    ->orWhereHas('subtasks.assignees', fn($assignees) => $assignees->where('users.id', $user->id));
            })
            ->with(['taskList.space.workspace', 'status', 'labels', 'assignees', 'subtasks.assignees'])
            ->orderBy('position');

        return $this->applyFilters($query, $filters)->distinct()->get();
    }
}
