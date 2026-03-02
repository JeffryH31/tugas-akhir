<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Priority;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * Get all workspaces for a user
     */
    public function getWorkspacesForUser(User $user): Collection
    {
        return $user->workspaces()
            ->with(['spaces' => fn($q) => $q->orderBy('position')])
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new workspace
     */
    public function create(array $data, User $owner): Workspace
    {
        return DB::transaction(function () use ($data, $owner) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#7C3AED',
                'icon' => $data['icon'] ?? null,
                'owner_id' => $owner->id,
                'is_personal' => $data['is_personal'] ?? false,
            ]);

            $this->createDefaultPriorities($workspace);

            Activity::log($workspace, $owner, $workspace, 'created', [
                'name' => $workspace->name,
            ]);

            return $workspace;
        });
    }

    /**
     * Update a workspace
     */
    public function update(Workspace $workspace, array $data, User $user): Workspace
    {
        $oldName = $workspace->name;

        $workspace->update([
            'name' => $data['name'] ?? $workspace->name,
            'description' => $data['description'] ?? $workspace->description,
            'color' => $data['color'] ?? $workspace->color,
            'icon' => $data['icon'] ?? $workspace->icon,
        ]);

        if ($oldName !== $workspace->name) {
            Activity::log($workspace, $user, $workspace, 'updated', [
                'name' => $workspace->name,
            ], [
                'name' => ['old' => $oldName, 'new' => $workspace->name],
            ]);
        }

        return $workspace->fresh();
    }

    /**
     * Delete a workspace
     */
    public function delete(Workspace $workspace, User $user): void
    {
        DB::transaction(function () use ($workspace, $user) {
            // Log before deletion
            Activity::log($workspace, $user, $workspace, 'deleted', [
                'name' => $workspace->name,
            ]);

            $workspace->delete();
        });
    }

    /**
     * Add a member to the workspace
     */
    public function addMember(Workspace $workspace, User $user, string $role = 'member', ?User $addedBy = null): void
    {
        $workspace->addMember($user, $role);

        if ($addedBy) {
            Activity::log($workspace, $addedBy, $workspace, 'member_added', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'member_id' => $user->id,
                'role' => $role,
            ]);
        }
    }

    /**
     * Remove a member from the workspace
     */
    public function removeMember(Workspace $workspace, User $user, ?User $removedBy = null): void
    {
        $workspace->removeMember($user);

        if ($removedBy) {
            Activity::log($workspace, $removedBy, $workspace, 'member_removed', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'member_id' => $user->id,
            ]);
        }
    }

    /**
     * Update member role
     */
    public function updateMemberRole(Workspace $workspace, User $user, string $role, ?User $updatedBy = null): void
    {
        $workspace->members()->updateExistingPivot($user->id, ['role' => $role]);

        if ($updatedBy) {
            Activity::log($workspace, $updatedBy, $workspace, 'member_role_updated', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'role' => $role,
            ]);
        }
    }

    /**
     * Get workspace statistics
     */
    public function getStatistics(Workspace $workspace): array
    {
        $spaces = $workspace->spaces()->withCount([
            'lists',
        ])->get();

        $totalLists = $spaces->sum('lists_count');
        
        // Get task counts through relationships
        $taskCounts = DB::table('tasks')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->join('spaces', 'task_lists.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->whereNull('tasks.deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->first();

        // Get subtask counts for completion and overdue metrics
        $subtaskCounts = DB::table('subtasks')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->join('spaces', 'task_lists.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->whereNull('subtasks.deleted_at')
            ->selectRaw('
                SUM(CASE WHEN subtasks.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN subtasks.completed_at IS NULL AND subtasks.due_date < NOW() THEN 1 ELSE 0 END) as overdue
            ')
            ->first();

        return [
            'spaces_count' => $spaces->count(),
            'lists_count' => $totalLists,
            'tasks_count' => $taskCounts->total ?? 0,
            'completed_subtasks_count' => $subtaskCounts->completed ?? 0,
            'overdue_subtasks_count' => $subtaskCounts->overdue ?? 0,
            'members_count' => $workspace->members()->count(),
        ];
    }

    /**
     * Create default priorities for workspace
     */
    protected function createDefaultPriorities(Workspace $workspace): void
    {
        $priorities = [
            ['name' => 'Urgent', 'color' => '#EF4444', 'level' => 4, 'icon' => 'mdi-flag'],
            ['name' => 'High', 'color' => '#F59E0B', 'level' => 3, 'icon' => 'mdi-flag'],
            ['name' => 'Normal', 'color' => '#3B82F6', 'level' => 2, 'icon' => 'mdi-flag', 'is_default' => true],
            ['name' => 'Low', 'color' => '#6B7280', 'level' => 1, 'icon' => 'mdi-flag'],
        ];

        foreach ($priorities as $priority) {
            $workspace->priorities()->create($priority);
        }
    }
}
