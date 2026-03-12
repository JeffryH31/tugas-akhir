<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
                'time_estimate' => $data['time_estimate'] ?? null,
                'created_by' => $user->id,
            ]);

            // Sync assignees
            if (!empty($data['assignee_ids'])) {
                $assignees = [];
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $assignees[$assigneeId] = [
                        'assigned_at' => now(),
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
            $subtask->update(array_filter([
                'name' => $data['name'] ?? $subtask->name,
                'description' => $data['description'] ?? $subtask->description,
                'status_id' => $data['status_id'] ?? $subtask->status_id,
                'priority_level' => $data['priority_level'] ?? $subtask->priority_level,
                'start_date' => $data['start_date'] ?? $subtask->start_date,
                'due_date' => $data['due_date'] ?? $subtask->due_date,
                'time_estimate' => $data['time_estimate'] ?? $subtask->time_estimate,
            ], fn($value) => !is_null($value)));

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $assignees = [];
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $assignees[$assigneeId] = [
                        'assigned_at' => now(),
                        'assigned_by' => $user->id,
                    ];
                }
                $subtask->assignees()->sync($assignees);
            }

            // Update labels if provided
            if (isset($data['label_ids'])) {
                $subtask->labels()->sync($data['label_ids']);
            }

            Activity::log(
                $subtask->task->taskList->space->workspace,
                $user,
                $subtask,
                'updated',
                ['name' => $subtask->name]
            );

            return $subtask->fresh();
        });
    }

    /**
     * Delete a subtask
     */
    public function delete(Subtask $subtask, User $user): void
    {
        DB::transaction(function () use ($subtask, $user) {
            $subtaskName = $subtask->name;

            $subtask->delete();

            Activity::log(
                $subtask->task->taskList->space->workspace,
                $user,
                $subtask->task,
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
