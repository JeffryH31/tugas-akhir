<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Label;
use App\Models\Priority;
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
            ->root()
            ->with([
                'status',
                'priority',
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
            'priority',
            'assignees',
            'labels',
            'watchers',
            'subtasks' => fn($q) => $q->with(['status', 'assignees', 'subtasks'])->orderBy('position'),
            'parent',
            'dependencies',
            'dependents',
            'comments' => fn($q) => $q->with(['user', 'replies.user']),
            'timeEntries' => fn($q) => $q->with('user')->latest(),
            'attachments',
            'creator',
        ]);
    }

    /**
     * Create a new task
     */
    public function create(array $data, TaskList $list, User $user, ?Task $parent = null): Task
    {
        return DB::transaction(function () use ($data, $list, $user, $parent) {
            $task = Task::create([
                'task_list_id' => $list->id,
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'priority_id' => $data['priority_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'time_estimate' => $data['time_estimate'] ?? null,
                'created_by' => $user->id,
            ]);

            // Assign users if provided
            if (!empty($data['assignee_ids'])) {
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $assignee = User::findOrFail($assigneeId);
                    $task->assign($assignee, $user);
                }
            }

            // Add labels if provided
            if (!empty($data['label_ids'])) {
                $task->labels()->sync($data['label_ids']);
            }

            Activity::log($list->space->workspace, $user, $task, 'created', [
                'name' => $task->name,
            ]);

            return $task->fresh(['status', 'priority', 'assignees', 'labels']);
        });
    }

    /**
     * Update a task
     */
    public function update(Task $task, array $data, User $user): Task
    {
        return DB::transaction(function () use ($task, $data, $user) {
            $changes = [];
            $oldValues = $task->only([
                'name', 'description', 'status_id', 'priority_id',
                'start_date', 'due_date', 'time_estimate'
            ]);

            $task->update([
                'name' => $data['name'] ?? $task->name,
                'description' => $data['description'] ?? $task->description,
                'status_id' => $data['status_id'] ?? $task->status_id,
                'priority_id' => $data['priority_id'] ?? $task->priority_id,
                'start_date' => $data['start_date'] ?? $task->start_date,
                'due_date' => $data['due_date'] ?? $task->due_date,
                'time_estimate' => $data['time_estimate'] ?? $task->time_estimate,
            ]);

            // Track changes
            foreach ($oldValues as $key => $oldValue) {
                $newValue = $data[$key] ?? $task->$key;
                if ($newValue != $oldValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $oldAssignees = $task->assignees->pluck('id')->toArray();
                $task->assignees()->sync($data['assignee_ids']);
                
                if ($oldAssignees != $data['assignee_ids']) {
                    $changes['assignees'] = ['old' => $oldAssignees, 'new' => $data['assignee_ids']];
                }
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
     * Complete a task
     */
    public function complete(Task $task, User $user): Task
    {
        $task->complete($user);

        Activity::log($task->taskList->space->workspace, $user, $task, 'completed', [
            'name' => $task->name,
        ]);

        return $task->fresh();
    }

    /**
     * Reopen a task
     */
    public function reopen(Task $task, User $user): Task
    {
        $task->reopen();

        Activity::log($task->taskList->space->workspace, $user, $task, 'reopened', [
            'name' => $task->name,
        ]);

        return $task->fresh();
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
    public function changePriority(Task $task, ?Priority $priority, User $user): Task
    {
        $oldPriority = $task->priority;
        $task->update(['priority_id' => $priority?->id]);

        Activity::log($task->taskList->space->workspace, $user, $task, 'priority_changed', [
            'name' => $task->name,
        ], [
            'priority' => [
                'old' => $oldPriority?->name,
                'new' => $priority?->name,
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
        DB::transaction(function () use ($order) {
            foreach ($order as $position => $taskId) {
                Task::where('id', $taskId)->update(['position' => $position]);
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
            $newTask = $task->replicate([
                'task_id', 'completed_at', 'completed_by', 'time_spent'
            ]);
            $newTask->name = $task->name . ' (Copy)';
            $newTask->position = Task::where('task_list_id', $task->task_list_id)
                ->whereNull('parent_id')
                ->max('position') + 1;
            $newTask->save();

            // Copy assignees
            $newTask->assignees()->sync($task->assignees->pluck('id'));

            // Copy labels
            $newTask->labels()->sync($task->labels->pluck('id'));

            // Duplicate subtasks
            foreach ($task->subtasks as $subtask) {
                $newSubtask = $subtask->replicate([
                    'task_id', 'completed_at', 'completed_by', 'time_spent'
                ]);
                $newSubtask->parent_id = $newTask->id;
                $newSubtask->save();
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

        if (!empty($filters['priority_ids'])) {
            $query->whereIn('priority_id', $filters['priority_ids']);
        }

        if (!empty($filters['assignee_ids'])) {
            $query->whereHas('assignees', fn($q) => $q->whereIn('user_id', $filters['assignee_ids']));
        }

        if (!empty($filters['label_ids'])) {
            $query->whereHas('labels', fn($q) => $q->whereIn('label_id', $filters['label_ids']));
        }

        if (!empty($filters['is_completed'])) {
            $query->whereNotNull('completed_at');
        } elseif (isset($filters['is_completed']) && $filters['is_completed'] === false) {
            $query->whereNull('completed_at');
        }

        if (!empty($filters['is_overdue'])) {
            $query->overdue();
        }

        if (!empty($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (!empty($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
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
        $query = $user->assignedTasks()
            ->with(['taskList.space.workspace', 'status', 'priority', 'labels'])
            ->whereNull('completed_at')
            ->orderBy('due_date');

        return $this->applyFilters($query, $filters)->get();
    }
}
