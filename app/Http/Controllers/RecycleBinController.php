<?php

namespace App\Http\Controllers;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecycleBinController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $taskLists = TaskList::onlyTrashed()
            ->whereHas('space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with('space:id,name')
            ->latest('deleted_at')
            ->get(['id', 'space_id', 'name', 'deleted_at']);

        $tasks = Task::onlyTrashed()
            ->whereHas('taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['taskList:id,name,space_id', 'taskList.space:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'task_list_id', 'name', 'task_id', 'deleted_at']);

        $subtasks = Subtask::onlyTrashed()
            ->whereHas('task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['task:id,name,task_list_id', 'task.taskList:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'task_id', 'name', 'subtask_id', 'deleted_at']);

        $timeEntries = TimeEntry::onlyTrashed()
            ->whereHas('subtask.task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['user:id,name', 'subtask:id,name'])
            ->latest('deleted_at')
            ->get(['id', 'subtask_id', 'user_id', 'duration', 'deleted_at']);

        return Inertia::render('Workspaces/RecycleBin', [
            'workspace' => $workspace,
            'trash' => [
                'lists' => $taskLists,
                'tasks' => $tasks,
                'subtasks' => $subtasks,
                'time_entries' => $timeEntries,
            ],
        ]);
    }

    public function restore(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:list,task,subtask,time_entry'],
            'id' => ['required', 'integer'],
        ]);

        try {
            match ($validated['type']) {
                'list' => TaskList::onlyTrashed()
                    ->where('id', $validated['id'])
                    ->whereHas('space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'task' => Task::onlyTrashed()
                    ->where('id', $validated['id'])
                    ->whereHas('taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'subtask' => Subtask::onlyTrashed()
                    ->where('id', $validated['id'])
                    ->whereHas('task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
                'time_entry' => TimeEntry::onlyTrashed()
                    ->where('id', $validated['id'])
                    ->whereHas('subtask.task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->firstOrFail()
                    ->restore(),
            };

            return back()->with('success', 'Item restored successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to restore item.']);
        }
    }
}
