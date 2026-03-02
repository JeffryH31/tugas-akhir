<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreTaskListRequest;
use App\Http\Requests\UpdateTaskListRequest;
use App\Models\Folder;
use App\Models\Space;
use App\Models\TaskList;
use App\Models\Workspace;
use App\Services\TaskListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskListController extends Controller
{
    public function __construct(
        protected TaskListService $taskListService
    ) {}

    /**
     * Store a newly created list.
     */
    public function store(StoreTaskListRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $folder = $request->folder_id ? Folder::find($request->folder_id) : null;

        $list = $this->taskListService->create(
            $request->validated(),
            $space,
            $request->user(),
            $folder
        );

        return redirect()
            ->route('lists.show', [$workspace, $space, $list])
            ->with('success', 'List created successfully.');
    }

    /**
     * Display the specified list (Board view).
     */
    public function show(Request $request, Workspace $workspace, Space $space, TaskList $list): Response
    {
        $taskId = $request->query('task_id');
        $tasksByStatus = $this->taskListService->getWithTasksByStatus($list, $taskId);

        $workspace->load([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
            'members',
            'priorities',
            'labels',
        ]);

        // Filter statuses based on whether viewing subtasks or tasks
        $statusesQuery = $space->statuses()->orderBy('position');
        if ($taskId) {
            $statusesQuery->forSubtasks(); // Only subtask-applicable statuses
        } else {
            $statusesQuery->forTasks(); // Only task-applicable statuses
        }

        return Inertia::render('Lists/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
            'tasksByStatus' => $tasksByStatus,
            'statuses' => $statusesQuery->get(),
            'sprints' => $space->sprints()->orderBy('position')->get(),
            'parentTask' => $taskId ? \App\Models\Task::find($taskId) : null,
        ]);
    }

    /**
     * Update the specified list.
     */
    public function update(UpdateTaskListRequest $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $this->taskListService->update($list, $request->validated(), $request->user());

        return back()->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified list.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $this->taskListService->delete($list, $request->user());

        return redirect()
            ->route('spaces.show', [$workspace, $space])
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Archive the specified list.
     */
    public function archive(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $this->taskListService->archive($list, $request->user());

        return back()->with('success', 'List archived successfully.');
    }

    /**
     * Unarchive the specified list.
     */
    public function unarchive(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $this->taskListService->unarchive($list, $request->user());

        return back()->with('success', 'List restored successfully.');
    }

    /**
     * Move list to folder.
     */
    public function moveToFolder(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $request->validate([
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $folder = $request->folder_id ? Folder::find($request->folder_id) : null;

        $this->taskListService->moveToFolder($list, $folder, $request->user());

        return back()->with('success', 'List moved successfully.');
    }

    /**
     * Reorder lists.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $this->taskListService->reorder($request->order);

        return back();
    }

    /**
     * Duplicate the list.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        $newList = $this->taskListService->duplicate($list, $request->user());

        return redirect()
            ->route('lists.show', [$workspace, $space, $newList])
            ->with('success', 'List duplicated successfully.');
    }
}
