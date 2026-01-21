<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Space;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SpaceService
 *
 * Handles Space-related business logic .
 */
class SpaceService
{
    /**
     * Create a new space within a workspace.
     */
    public function create(Workspace $workspace, array $data, User $creator): Space
    {
        return DB::transaction(function () use ($workspace, $data, $creator) {
            $maxPosition = $workspace->spaces()->max('position') ?? -1;

            $space = Space::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'color' => $data['color'] ?? '#6366F1',
                'is_private' => $data['is_private'] ?? false,
                'position' => $maxPosition + 1,
                'features' => $data['features'] ?? null,
            ]);

            // Add creator as member
            $space->members()->attach($creator->id, ['role' => 'admin']);

            return $space;
        });
    }

    /**
     * Update a space.
     */
    public function update(Space $space, array $data): Space
    {
        $space->update([
            'name' => $data['name'] ?? $space->name,
            'description' => $data['description'] ?? $space->description,
            'color' => $data['color'] ?? $space->color,
            'is_private' => $data['is_private'] ?? $space->is_private,
            'features' => $data['features'] ?? $space->features,
        ]);

        return $space->fresh();
    }

    /**
     * Delete (soft delete) a space.
     */
    public function delete(Space $space): bool
    {
        return $space->delete();
    }

    /**
     * Get all spaces accessible by a user within a workspace.
     */
    public function getAccessibleSpaces(Workspace $workspace, User $user): Collection
    {
        return $workspace->spaces()
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhereHas('members', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->orWhere('created_by', $user->id);
            })
            ->active()
            ->orderBy('position')
            ->get();
    }

    /**
     * Add a member to a space.
     */
    public function addMember(Space $space, User $user, string $role = 'member'): void
    {
        if (!$space->members()->where('user_id', $user->id)->exists()) {
            $space->members()->attach($user->id, ['role' => $role]);
        }
    }

    /**
     * Remove a member from a space.
     */
    public function removeMember(Space $space, User $user): void
    {
        $space->members()->detach($user->id);
    }

    /**
     * Update member role in a space.
     */
    public function updateMemberRole(Space $space, User $user, string $role): void
    {
        $space->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Toggle star status for a space.
     */
    public function toggleStar(Space $space): Space
    {
        $space->update(['is_starred' => !$space->is_starred]);
        return $space->fresh();
    }

    /**
     * Reorder spaces within a workspace.
     */
    public function reorder(Workspace $workspace, array $spaceIds): void
    {
        DB::transaction(function () use ($workspace, $spaceIds) {
            foreach ($spaceIds as $position => $spaceId) {
                $workspace->spaces()
                    ->where('id', $spaceId)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a space with all its content.
     */
    public function duplicate(Space $space, string $newName = null): Space
    {
        return DB::transaction(function () use ($space, $newName) {
            $newSpace = $space->replicate();
            $newSpace->name = $newName ?? $space->name . ' (Copy)';
            $newSpace->position = $space->workspace->spaces()->max('position') + 1;
            $newSpace->save();

            // Copy members
            foreach ($space->members as $member) {
                $newSpace->members()->attach($member->id, [
                    'role' => $member->pivot->role,
                ]);
            }

            // Copy folders and lists would go here
            // This is simplified - full implementation would recursively copy all content

            return $newSpace;
        });
    }
}
