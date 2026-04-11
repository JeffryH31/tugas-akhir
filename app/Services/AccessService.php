<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

/**
 * Centralized authorization helper for workspace, space, and product scopes.
 */
class AccessService
{
    public const WORKSPACE_OWNER = 'owner';
    public const WORKSPACE_ADMIN = 'admin';
    public const WORKSPACE_MEMBER = 'member';
    public const WORKSPACE_GUEST = 'guest';

    public const SPACE_OWNER = 'owner';
    public const SPACE_ADMIN = 'admin';
    public const SPACE_MANAGER = 'manager';
    public const SPACE_MEMBER = 'member';
    public const SPACE_GUEST = 'guest';

    public const PROJECT_OWNER = 'project_owner';
    public const PROJECT_MANAGER = 'project_manager';
    public const PROJECT_DEVELOPER = 'development_team';
    public const PROJECT_GUEST = 'guest';

    /**
     * Determine whether the user can access the application.
     *
     * @param User $user
     * @return bool
     */
    public function canAccessWebsite(User $user): bool
    {
        return !is_null($user->id) && $user->workspaces()->exists();
    }

    /**
     * Get workspace membership role for a user.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return string|null
     */
    public function getWorkspaceRole(User $user, Workspace $workspace): ?string
    {
        return $workspace->members()->where('user_id', $user->id)->first()?->pivot?->role;
    }

    /**
     * Get space membership role for a user.
     *
     * @param User $user
     * @param Space $space
     * @return string|null
     */
    public function getSpaceRole(User $user, Space $space): ?string
    {
        return $space->members()->where('user_id', $user->id)->first()?->pivot?->role;
    }

    /**
     * Get product/project membership role for a user.
     *
     * @param User $user
     * @param TaskList $list
     * @return string|null
     */
    public function getProjectRole(User $user, TaskList $list): ?string
    {
        return $list->members()->where('user_id', $user->id)->first()?->pivot?->role;
    }

    /**
     * Alias of getProjectRole for product terminology.
     *
     * @param User $user
     * @param TaskList $list
     * @return string|null
     */
    public function getProductRole(User $user, TaskList $list): ?string
    {
        return $this->getProjectRole($user, $list);
    }

    /**
     * Determine whether a user can view a workspace.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        return !is_null($this->getWorkspaceRole($user, $workspace));
    }

    /**
     * Determine whether a user can manage workspace settings and members.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function canManageWorkspace(User $user, Workspace $workspace): bool
    {
        return in_array($this->getWorkspaceRole($user, $workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true);
    }

    /**
     * Determine whether a user can view a space.
     *
     * @param User $user
     * @param Space $space
     * @return bool
     */
    public function canViewSpace(User $user, Space $space): bool
    {
        if (!$this->canViewWorkspace($user, $space->workspace)) {
            return false;
        }

        $workspaceRole = $this->getWorkspaceRole($user, $space->workspace);

        if (in_array($workspaceRole, [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if (!$space->is_private) {
            return in_array($workspaceRole, [self::WORKSPACE_MEMBER, self::WORKSPACE_GUEST], true);
        }

        return in_array($this->getSpaceRole($user, $space), [
            self::SPACE_OWNER,
            self::SPACE_ADMIN,
            self::SPACE_MANAGER,
            self::SPACE_MEMBER,
            self::SPACE_GUEST,
        ], true);
    }

    /**
     * Determine whether a user can manage a space.
     *
     * @param User $user
     * @param Space $space
     * @return bool
     */
    public function canManageSpace(User $user, Space $space): bool
    {
        if (in_array($this->getWorkspaceRole($user, $space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if (!$this->canViewSpace($user, $space)) {
            return false;
        }

        return in_array($this->getSpaceRole($user, $space), [
            self::SPACE_OWNER,
            self::SPACE_ADMIN,
            self::SPACE_MANAGER,
        ], true);
    }

    /**
     * Determine whether a user can view a product.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canViewProduct(User $user, TaskList $list): bool
    {
        if (!$this->canViewSpace($user, $list->space)) {
            return false;
        }

        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if (in_array($this->getProductRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
            self::PROJECT_GUEST,
        ], true)) {
            return true;
        }

        // Backward compatibility: if product membership has not been configured,
        // inherit view access from the parent space.
        return !$list->members()->exists();
    }

    /**
     * Alias of canViewProduct for project terminology.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canViewProject(User $user, TaskList $list): bool
    {
        return $this->canViewProduct($user, $list);
    }

    /**
     * Determine whether a user can manage a product.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageProduct(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProductRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Alias of canManageProduct for project terminology.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageProject(User $user, TaskList $list): bool
    {
        return $this->canManageProduct($user, $list);
    }

    /**
     * Determine whether a user can delete a product.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canDeleteProduct(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return $this->getProductRole($user, $list) === self::PROJECT_OWNER;
    }

    /**
     * Alias of canDeleteProduct for project terminology.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canDeleteProject(User $user, TaskList $list): bool
    {
        return $this->canDeleteProduct($user, $list);
    }

    /**
     * Determine whether a user can manage product members.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageProductMembers(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return $this->getProductRole($user, $list) === self::PROJECT_OWNER;
    }

    /**
     * Alias of canManageProductMembers for project terminology.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageProjectMembers(User $user, TaskList $list): bool
    {
        return $this->canManageProductMembers($user, $list);
    }

    /**
     * Determine whether a user can edit tasks in a product.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canEditTasks(User $user, TaskList $list): bool
    {
        return $this->canOperateTasks($user, $list);
    }

    /**
     * Determine whether a user can perform task operations.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canOperateTasks(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
        ], true);
    }

    /**
     * Determine whether a user can manage task structure resources.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageTaskStructure(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can manage labels.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageLabels(User $user, TaskList $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    /**
     * Determine whether a user can manage dependencies.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canManageDependencies(User $user, TaskList $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    /**
     * Determine whether a user can assign tasks.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canAssignTasks(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can track time.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canTrackTime(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
        ], true);
    }

    /**
     * Determine whether a user can comment on a product.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canComment(User $user, TaskList $list): bool
    {
        return $this->canViewProject($user, $list);
    }

    /**
     * Determine whether a user can manage a specific comment.
     *
     * @param User $user
     * @param Comment $comment
     * @return bool
     */
    public function canManageComment(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    /**
     * Determine whether a user can manage a specific time entry.
     *
     * @param User $user
     * @param TimeEntry $entry
     * @return bool
     */
    public function canManageTimeEntry(User $user, TimeEntry $entry): bool
    {
        if ($entry->user_id === $user->id) {
            return true;
        }

        return $this->canAssignTasks($user, $entry->subtask->task->taskList);
    }

    /**
     * Determine whether a user can delete a workspace (owner only).
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function canDeleteWorkspace(User $user, Workspace $workspace): bool
    {
        return (int) $workspace->owner_id === (int) $user->id;
    }

    /**
     * Determine whether a user can view workspace analytics.
     *
     * @param User $user
     * @param Workspace $workspace
     * @return bool
     */
    public function canViewAnalytics(User $user, Workspace $workspace): bool
    {
        return in_array($this->getWorkspaceRole($user, $workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
            self::WORKSPACE_MEMBER,
        ], true);
    }

    /**
     * Determine whether a user can view project activity logs.
     *
     * @param User $user
     * @param TaskList $list
     * @return bool
     */
    public function canViewActivity(User $user, TaskList $list): bool
    {
        return $this->canViewProject($user, $list);
    }
}
