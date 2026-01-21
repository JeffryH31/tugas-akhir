<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * WorkspacePolicy
 *
 * Authorization policy for Workspace model.
 * Determines what actions a user can perform on a workspace.
 */
class WorkspacePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any workspaces.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function view(User $user, Workspace $workspace): bool
    {
        // Owner can always view
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        // Member can view
        return $workspace->hasMember($user);
    }

    /**
     * Determine whether the user can create workspaces.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function update(User $user, Workspace $workspace): bool
    {
        // Owner can always update
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        // Admin members can update
        return $workspace->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        // Only owner can delete
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function restore(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function forceDelete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can manage workspace members.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        // Owner can always manage members
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        // Admin members can manage members
        return $workspace->isAdmin($user);
    }
}
