<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WorkspaceService 
 *
 * Handles all business logic related to workspaces.
 * Updated for Hierarchy: Workspace -> Space -> Folder -> List -> Task
 */
class WorkspaceService
{
    /**
     * Get all workspaces accessible by a user.
     */
    public function getAccessibleWorkspaces(User $user): Collection
    {
        return Workspace::query()
            ->active()
            ->accessibleBy($user->id)
            ->with(['spaces' => fn($q) => $q->active()->orderBy('position')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a workspace with all related data.
     */
    public function getWorkspaceWithDetails(int $workspaceId): Workspace
    {
        return Workspace::query()
            ->with([
                'owner:id,name,email',
                'members:id,name,email',
                'spaces' => fn($q) => $q->active()->orderBy('position'),
            ])
            ->findOrFail($workspaceId);
    }

    /**
     * Create a new workspace.
     */
    public function createWorkspace(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#6366F1',
                'owner_id' => $owner->id,
            ]);

            // Add owner as a member with 'owner' role
            $workspace->members()->attach($owner->id, ['role' => 'owner']);

            return $workspace;
        });
    }

    /**
     * Update a workspace.
     */
    public function updateWorkspace(Workspace $workspace, array $data): Workspace
    {
        $workspace->update([
            'name' => $data['name'] ?? $workspace->name,
            'description' => $data['description'] ?? $workspace->description,
            'color' => $data['color'] ?? $workspace->color,
        ]);

        return $workspace->fresh();
    }

    /**
     * Delete a workspace (soft delete).
     */
    public function deleteWorkspace(Workspace $workspace): bool
    {
        return DB::transaction(function () use ($workspace) {
            // Soft delete all spaces in the workspace
            $workspace->spaces()->delete();

            // Soft delete the workspace
            return $workspace->delete();
        });
    }

    /**
     * Add a member to a workspace.
     */
    public function addMember(Workspace $workspace, User $user, string $role = 'member'): void
    {
        if (!$workspace->hasMember($user->id)) {
            $workspace->members()->attach($user->id, ['role' => $role]);
        }
    }

    /**
     * Remove a member from a workspace.
     */
    public function removeMember(Workspace $workspace, User $user): void
    {
        // Cannot remove the owner
        if ($workspace->owner_id === $user->id) {
            throw new \InvalidArgumentException('Cannot remove the workspace owner.');
        }

        $workspace->members()->detach($user->id);
    }

    /**
     * Update a member's role in the workspace.
     */
    public function updateMemberRole(Workspace $workspace, User $user, string $role): void
    {
        $workspace->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Get workspace statistics .
     */
    public function getWorkspaceStats(Workspace $workspace): array
    {
        $totalLists = 0;
        $totalTasks = 0;

        foreach ($workspace->spaces as $space) {
            $totalLists += $space->lists()->count();
            foreach ($space->lists as $list) {
                $totalTasks += $list->tasks()->count();
            }
        }

        return [
            'spaces_count' => $workspace->spaces()->count(),
            'members_count' => $workspace->members()->count(),
            'total_lists' => $totalLists,
            'total_tasks' => $totalTasks,
        ];
    }
}
