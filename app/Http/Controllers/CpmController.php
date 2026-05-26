<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\CpmService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CpmController extends Controller
{
    public function __construct(
        protected CpmService $cpmService,
        protected AccessService $accessService,
    ) {}

    /**
     * Get CPM analysis for a task's subtasks
     */
    public function analyze(
        Request $request,
        Workspace $workspace,
        Space $space,
        Project $list,
        Task $task
    ) {
        abort_unless($this->accessService->canViewProject($request->user(), $list), 403);
        if ($task->project_id !== $list->id) {
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
        Project $list,
        Task $task
    ): Response {
        abort_unless($this->accessService->canViewProject($request->user(), $list), 403);
        if ($task->project_id !== $list->id) {
            abort(404);
        }

        $cpmResult = $this->cpmService->analyze($task);

        $user = $request->user();
        $isWsAdmin = $this->accessService->canManageWorkspace($user, $workspace);
        $listFilter = function ($q) use ($user, $isWsAdmin) {
            return $isWsAdmin ? $q : $q->whereHas('members', fn($mq) => $mq->where('user_id', $user->id));
        };

        $workspace->load([
            'spaces' => function ($q) use ($user, $isWsAdmin, $listFilter) {
                if (!$isWsAdmin) {
                    $q->whereHas('members', fn($mq) => $mq->where('user_id', $user->id));
                }
                $q->with([
                    'folders.projects' => $listFilter,
                    'projectsWithoutFolder' => $listFilter,
                ])->orderBy('position');
            },
            'members',
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
            'task' => $task->load(['status', 'assignees']),
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
        Project $list,
        Task $task
    ) {
        abort_unless($this->accessService->canManageDependencies($request->user(), $list), 403);
        $validated = $request->validate([
            'subtask_id' => 'required|exists:subtasks,id',
            'depends_on_id' => 'required|exists:subtasks,id',
            'type' => 'nullable|in:blocks,relates_to',
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
        Project $list,
        Task $task
    ) {
        abort_unless($this->accessService->canManageDependencies($request->user(), $list), 403);
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
