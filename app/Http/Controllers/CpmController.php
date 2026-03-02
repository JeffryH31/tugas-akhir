<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\Workspace;
use App\Services\CpmService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CpmController extends Controller
{
    public function __construct(
        protected CpmService $cpmService
    ) {}

    /**
     * Get CPM analysis for a task's subtasks
     */
    public function analyze(
        Request $request,
        Workspace $workspace,
        Space $space,
        TaskList $list,
        Task $task
    ) {
        if ($task->task_list_id !== $list->id) {
            abort(404);
        }

        $result = $this->cpmService->analyze($task);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return back()->with('cpm', $result);
    }

    /**
     * Show Gantt view for a task's subtasks
     */
    public function gantt(
        Request $request,
        Workspace $workspace,
        Space $space,
        TaskList $list,
        Task $task
    ): Response {
        if ($task->task_list_id !== $list->id) {
            abort(404);
        }

        $cpmResult = $this->cpmService->analyze($task);

        $workspace->load([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
            'members',
            'priorities',
            'labels',
        ]);

        $statuses = $space->statuses()
            ->forSubtasks()
            ->orderBy('position')
            ->get();

        return Inertia::render('Tasks/Gantt', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
            'task' => $task->load(['status', 'priority', 'assignees']),
            'statuses' => $statuses,
            'cpm' => $cpmResult,
        ]);
    }

    /**
     * Add a dependency between two subtasks
     */
    public function addDependency(
        Request $request,
        Workspace $workspace,
        Space $space,
        TaskList $list,
        Task $task
    ) {
        $validated = $request->validate([
            'subtask_id' => 'required|exists:subtasks,id',
            'depends_on_id' => 'required|exists:subtasks,id',
            'type' => 'nullable|in:blocks,blocked_by,relates_to',
        ]);

        $subtask = Subtask::findOrFail($validated['subtask_id']);
        $dependsOn = Subtask::findOrFail($validated['depends_on_id']);

        if ($subtask->task_id !== $task->id || $dependsOn->task_id !== $task->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subtasks must belong to the same task',
            ], 422);
        }

        $result = $this->cpmService->addDependency(
            $subtask,
            $dependsOn,
            $validated['type'] ?? 'blocks'
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Remove a dependency between two subtasks
     */
    public function removeDependency(
        Request $request,
        Workspace $workspace,
        Space $space,
        TaskList $list,
        Task $task
    ) {
        $validated = $request->validate([
            'subtask_id' => 'required|exists:subtasks,id',
            'depends_on_id' => 'required|exists:subtasks,id',
        ]);

        $subtask = Subtask::findOrFail($validated['subtask_id']);
        $dependsOn = Subtask::findOrFail($validated['depends_on_id']);

        $result = $this->cpmService->removeDependency($subtask, $dependsOn);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return back()->with('success', $result['message']);
    }
}
