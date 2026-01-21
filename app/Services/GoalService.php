<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalTarget;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GoalService
 *
 * Handles Goal-related business logic .
 */
class GoalService
{
    /**
     * Create a new goal.
     */
    public function create(Workspace $workspace, User $owner, array $data): Goal
    {
        return DB::transaction(function () use ($workspace, $owner, $data) {
            $goal = Goal::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'workspace_id' => $workspace->id,
                'owner_id' => $owner->id,
                'due_date' => $data['due_date'] ?? null,
                'color' => $data['color'] ?? '#6366F1',
                'is_private' => $data['is_private'] ?? false,
            ]);

            // Create targets if provided
            if (!empty($data['targets'])) {
                foreach ($data['targets'] as $targetData) {
                    $this->addTarget($goal, $targetData);
                }
            }

            return $goal->load('targets');
        });
    }

    /**
     * Update a goal.
     */
    public function update(Goal $goal, array $data): Goal
    {
        $goal->update([
            'name' => $data['name'] ?? $goal->name,
            'description' => $data['description'] ?? $goal->description,
            'due_date' => $data['due_date'] ?? $goal->due_date,
            'status' => $data['status'] ?? $goal->status,
            'color' => $data['color'] ?? $goal->color,
            'is_private' => $data['is_private'] ?? $goal->is_private,
        ]);

        return $goal->fresh();
    }

    /**
     * Delete a goal.
     */
    public function delete(Goal $goal): bool
    {
        return DB::transaction(function () use ($goal) {
            $goal->targets()->delete();
            return $goal->delete();
        });
    }

    /**
     * Add a target to a goal.
     */
    public function addTarget(Goal $goal, array $data): GoalTarget
    {
        return $goal->targets()->create([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'number',
            'target_value' => $data['target_value'] ?? 0,
            'current_value' => $data['current_value'] ?? 0,
            'unit' => $data['unit'] ?? null,
            'linked_list_id' => $data['linked_list_id'] ?? null,
        ]);
    }

    /**
     * Update a target.
     */
    public function updateTarget(GoalTarget $target, array $data): GoalTarget
    {
        $target->update([
            'name' => $data['name'] ?? $target->name,
            'target_value' => $data['target_value'] ?? $target->target_value,
            'current_value' => $data['current_value'] ?? $target->current_value,
            'unit' => $data['unit'] ?? $target->unit,
        ]);

        // Update goal progress
        $target->goal->updateProgress();

        return $target->fresh();
    }

    /**
     * Delete a target.
     */
    public function deleteTarget(GoalTarget $target): bool
    {
        $goal = $target->goal;
        $result = $target->delete();
        $goal->updateProgress();
        return $result;
    }

    /**
     * Increment target progress.
     */
    public function incrementTarget(GoalTarget $target, float $amount = 1): GoalTarget
    {
        $target->increment('current_value', $amount);
        $target->goal->updateProgress();
        return $target->fresh();
    }

    /**
     * Get goals for a workspace.
     */
    public function getGoalsForWorkspace(Workspace $workspace, User $user): Collection
    {
        return $workspace->goals()
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_id', $user->id);
            })
            ->with(['owner', 'targets'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get goals owned by a user.
     */
    public function getGoalsForUser(User $user): Collection
    {
        return $user->goals()
            ->with(['workspace', 'targets'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update goal status.
     */
    public function updateStatus(Goal $goal, string $status): Goal
    {
        $goal->update(['status' => $status]);
        return $goal->fresh();
    }

    /**
     * Mark goal as completed.
     */
    public function complete(Goal $goal): Goal
    {
        $goal->update([
            'status' => Goal::STATUS_COMPLETED,
            'progress' => 100,
        ]);
        return $goal->fresh();
    }

    /**
     * Sync task completion targets.
     */
    public function syncTaskCompletionTargets(): void
    {
        GoalTarget::where('type', GoalTarget::TYPE_TASK_COMPLETION)
            ->whereNotNull('linked_list_id')
            ->each(function ($target) {
                $target->updateTaskCompletionProgress();
            });
    }
}
