<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

class AccessService
{
    public const WORKSPACE_OWNER = 'owner';
    public const WORKSPACE_ADMIN = 'admin';
    public const WORKSPACE_MEMBER = 'member';
    public const WORKSPACE_GUEST = 'guest';

    public const PROJECT_OWNER = 'project_owner';
    public const PROJECT_MANAGER = 'project_manager';
    public const PROJECT_DEVELOPER = 'development_team';
    public const PROJECT_GUEST = 'guest';

    public function getWorkspaceRole(User $user, Workspace $workspace): ?string
    {
        return $workspace->members()->where('user_id', $user->id)->first()?->pivot?->role;
    }

    public function getProjectRole(User $user, TaskList $list): ?string
    {
        return $list->members()->where('user_id', $user->id)->first()?->pivot?->role;
    }

    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        return !is_null($this->getWorkspaceRole($user, $workspace));
    }

    public function canManageWorkspace(User $user, Workspace $workspace): bool
    {
        return in_array($this->getWorkspaceRole($user, $workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true);
    }

    public function canViewSpace(User $user, Space $space): bool
    {
        $workspaceRole = $this->getWorkspaceRole($user, $space->workspace);

        if (in_array($workspaceRole, [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if ($workspaceRole === self::WORKSPACE_MEMBER) {
            return $space->members()->where('user_id', $user->id)->exists() || !$space->is_private;
        }

        return false;
    }

    public function canViewProject(User $user, TaskList $list): bool
    {
        $workspaceRole = $this->getWorkspaceRole($user, $list->space->workspace);
        if (in_array($workspaceRole, [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if ($workspaceRole === self::WORKSPACE_MEMBER && $this->canViewSpace($user, $list->space)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
            self::PROJECT_GUEST,
        ], true);
    }

    public function canManageProject(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    public function canDeleteProject(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return $this->getProjectRole($user, $list) === self::PROJECT_OWNER;
    }

    public function canManageProjectMembers(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return $this->getProjectRole($user, $list) === self::PROJECT_OWNER;
    }

    public function canEditTasks(User $user, TaskList $list): bool
    {
        return $this->canOperateTasks($user, $list);
    }

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

    public function canManageTaskStructure(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    public function canManageLabels(User $user, TaskList $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    public function canManageDependencies(User $user, TaskList $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    public function canAssignTasks(User $user, TaskList $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

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

    public function canComment(User $user, TaskList $list): bool
    {
        return $this->canViewProject($user, $list);
    }

    public function canManageComment(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    public function canManageTimeEntry(User $user, TimeEntry $entry): bool
    {
        if ($entry->user_id === $user->id) {
            return true;
        }

        return $this->canAssignTasks($user, $entry->subtask->task->taskList);
    }

    public function canViewAnalytics(User $user, Workspace $workspace): bool
    {
        return in_array($this->getWorkspaceRole($user, $workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
            self::WORKSPACE_MEMBER,
        ], true);
    }

    public function canViewActivity(User $user, TaskList $list): bool
    {
        return $this->canViewProject($user, $list);
    }
}
