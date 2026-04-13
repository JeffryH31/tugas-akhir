<?php

namespace App\Services;

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manage subtask CRUD, assignment, labels, and workflow transitions.
 */
class SubtaskService
{
    /**
     * Create a new subtask
     */
    public function create(array $data, Task $task, User $user): Subtask
    {
        return DB::transaction(function () use ($data, $task, $user) {
            $subtask = Subtask::create([
                'task_id' => $task->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'priority_level' => $data['priority_level'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'baseline_start_date' => $data['baseline_start_date'] ?? ($data['start_date'] ?? null),
                'baseline_due_date' => $data['baseline_due_date'] ?? ($data['due_date'] ?? null),
                'time_estimate' => $data['time_estimate'] ?? null,
                'optimistic_estimate' => $data['optimistic_estimate'] ?? null,
                'most_likely_estimate' => $data['most_likely_estimate'] ?? null,
                'pessimistic_estimate' => $data['pessimistic_estimate'] ?? null,
                'created_by' => $user->id,
            ]);

            // Sync assignees
            if (!empty($data['assignee_ids'])) {
                $assignees = [];
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $assignees[$assigneeId] = [
                        'assigned_by' => $user->id,
                    ];
                }
                $subtask->assignees()->sync($assignees);
            }

            // Sync labels
            if (!empty($data['label_ids'])) {
                $subtask->labels()->sync($data['label_ids']);
            }

            Activity::log(
                $task->taskList->space->workspace,
                $user,
                $subtask,
                'created',
                [
                    'name' => $subtask->name,
                    'task_name' => $task->name,
                ]
            );

            return $subtask;
        });
    }

    /**
     * Update a subtask
     */
    public function update(Subtask $subtask, array $data, User $user): Subtask
    {
        return DB::transaction(function () use ($subtask, $data, $user) {
            $updateData = [];
            $fields = [
                'name',
                'description',
                'status_id',
                'priority_level',
                'sprint_id',
                'start_date',
                'due_date',
                'baseline_start_date',
                'baseline_due_date',
                'time_estimate',
                'optimistic_estimate',
                'most_likely_estimate',
                'pessimistic_estimate',
                'progress',
            ];

            // Capture status/priority before update for readable activity descriptions
            $oldStatusId     = $subtask->status_id;
            $oldStatusName   = $subtask->status?->name;
            $oldPriorityEnum = $subtask->priority_level; // PriorityLevel enum|null

            // Capture old values for change tracking (status/priority logged separately below)
            $oldValues = $subtask->only(array_diff($fields, ['status_id', 'priority_level']));

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'sprint_id' && $data[$field]) {
                        $sprint = Sprint::find((int) $data[$field]);
                        if (!$sprint) {
                            throw ValidationException::withMessages([
                                'sprint_id' => ['Selected sprint does not exist.'],
                            ]);
                        }

                        $subtaskListId = (int) $subtask->task->task_list_id;
                        $sprintListId = (int) ($sprint->task_list_id ?? 0);

                        if ($sprintListId > 0 && $sprintListId !== $subtaskListId) {
                            throw ValidationException::withMessages([
                                'sprint_id' => ['Selected sprint is not in this product.'],
                            ]);
                        }
                    }

                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($updateData)) {
                $subtask->update($updateData);
            }

            // Track changes for activity
            $changes = [];
            $dateFields = ['start_date', 'due_date', 'baseline_start_date', 'baseline_due_date'];
            foreach ($oldValues as $key => $oldValue) {
                $newValue = array_key_exists($key, $data) ? $data[$key] : $subtask->$key;

                if (in_array($key, $dateFields, true)) {
                    $oldComparable = $this->normalizeDateForComparison($oldValue);
                    $newComparable = $this->normalizeDateForComparison($newValue);

                    if ($oldComparable === $newComparable) {
                        continue;
                    }

                    $changes[$key] = [
                        'old' => $oldComparable,
                        'new' => $newComparable,
                    ];

                    continue;
                }

                if ($newValue != $oldValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $oldAssigneeIds = $subtask->assignees->pluck('id')->toArray();
                $newAssigneeIds  = $data['assignee_ids'];

                $pivotData = [];
                foreach ($newAssigneeIds as $assigneeId) {
                    $pivotData[$assigneeId] = ['assigned_by' => $user->id];
                }
                $subtask->assignees()->sync($pivotData);

                $addedIds   = array_diff($newAssigneeIds, $oldAssigneeIds);
                $removedIds = array_diff($oldAssigneeIds, $newAssigneeIds);

                $workspace = $subtask->task->taskList->space->workspace;
                foreach ($addedIds as $id) {
                    $assignee = User::find($id);
                    if ($assignee) {
                        Activity::log($workspace, $user, $subtask, 'assigned', [
                            'name'          => $subtask->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id'   => $assignee->id,
                        ]);
                    }
                }

                foreach ($removedIds as $id) {
                    $assignee = User::find($id);
                    if ($assignee) {
                        Activity::log($workspace, $user, $subtask, 'unassigned', [
                            'name'          => $subtask->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id'   => $assignee->id,
                        ]);
                    }
                }
                // Assignee changes are logged separately above – don't add to generic $changes
            }

            // Update labels if provided
            if (isset($data['label_ids'])) {
                $subtask->labels()->sync($data['label_ids']);
            }

            // Log activity with changes
            if (!empty($changes)) {
                Activity::log(
                    $subtask->task->taskList->space->workspace,
                    $user,
                    $subtask,
                    'updated',
                    ['name' => $subtask->name],
                    $changes
                );
            }

            $workspace = $subtask->task->taskList->space->workspace;

            // Log status change with readable names
            if (isset($data['status_id']) && (int) $data['status_id'] !== (int) $oldStatusId) {
                $newStatus = Status::find($data['status_id']);
                Activity::log($workspace, $user, $subtask, 'status_changed', [
                    'name' => $subtask->name,
                ], [
                    'status' => ['old' => $oldStatusName, 'new' => $newStatus?->name],
                ]);
            }

            // Log priority change with readable labels
            if (array_key_exists('priority_level', $data) && $data['priority_level'] !== $oldPriorityEnum?->value) {
                $oldLabel = $oldPriorityEnum?->label();
                $newLabel = $data['priority_level'] !== null ? PriorityLevel::from((int) $data['priority_level'])->label() : null;
                Activity::log($workspace, $user, $subtask, 'priority_changed', [
                    'name' => $subtask->name,
                ], [
                    'priority' => ['old' => $oldLabel, 'new' => $newLabel],
                ]);
            }

            return $subtask->fresh();
        });
    }

    /**
     * Normalize date-like values into a comparable calendar date string.
     *
     * @param mixed $value
     * @return string|null
     */
    private function normalizeDateForComparison(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : null;
        }
    }

    /**
     * Delete a subtask
     */
    public function delete(Subtask $subtask, User $user): void
    {
        DB::transaction(function () use ($subtask, $user) {
            $subtaskName = $subtask->name;
            $task        = $subtask->task;
            $workspace   = $task->taskList->space->workspace;

            $subtask->delete();

            Activity::log(
                $workspace,
                $user,
                $task,
                'deleted_subtask',
                ['name' => $subtaskName]
            );
        });
    }

    /**
     * Reorder subtasks within a task
     */
    public function reorder(Task $task, array $subtaskIds): void
    {
        DB::transaction(function () use ($task, $subtaskIds) {
            foreach ($subtaskIds as $position => $subtaskId) {
                Subtask::where('id', $subtaskId)
                    ->where('task_id', $task->id)
                    ->update(['position' => $position]);
            }
        });
    }
}
