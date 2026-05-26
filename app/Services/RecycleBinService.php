<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class RecycleBinService
{
    /**
     * Get all trashed items for a workspace.
     */
    public function getTrashedItems(Workspace $workspace): array
    {
        $projects = Project::onlyTrashed()
            ->whereHas('space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with('space:id,name')
            ->latest('deleted_at')
            ->get(['id', 'space_id', 'name', 'deleted_at']);

        $tasks = Task::onlyTrashed()
            ->whereHas('project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['project:id,name,space_id', 'project.space:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'project_id', 'name', 'task_id', 'deleted_at']);

        $subtasks = Subtask::onlyTrashed()
            ->whereHas('task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['task:id,name,project_id', 'task.project:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'task_id', 'name', 'subtask_id', 'deleted_at']);

        $timeEntries = TimeEntry::onlyTrashed()
            ->whereHas('subtask.task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['user:id,name', 'subtask:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'subtask_id', 'user_id', 'duration', 'deleted_at']);

        return [
            'projects' => $projects,
            'tasks' => $tasks,
            'subtasks' => $subtasks,
            'time_entries' => $timeEntries,
        ];
    }

    /**
     * Restore a trashed item by type and ID.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function restoreItem(Workspace $workspace, string $type, int $id): void
    {
        DB::transaction(function () use ($workspace, $type, $id) {
            match ($type) {
                'list' => Project::onlyTrashed()
                    ->where('id', $id)
                    ->whereHas('space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'task' => Task::onlyTrashed()
                    ->where('id', $id)
                    ->whereHas('project.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'subtask' => Subtask::onlyTrashed()
                    ->where('id', $id)
                    ->whereHas('task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'time_entry' => TimeEntry::onlyTrashed()
                    ->where('id', $id)
                    ->whereHas('subtask.task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
            };
        });
    }
}
