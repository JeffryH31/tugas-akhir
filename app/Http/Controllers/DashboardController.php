<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\TaskService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
        protected TaskService $taskService
    ) {}

    /**
     * Display the main dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get user's workspaces with full hierarchy
        $workspaces = $this->workspaceService->getWorkspacesForUser($user);

        // Get active workspace (first one or from session)
        $activeWorkspaceId = session('active_workspace_id', $workspaces->first()?->id);
        $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId) ?? $workspaces->first();

        if ($activeWorkspace) {
            $activeWorkspace->load([
                'spaces' => fn($q) => $q->with([
                    'folders' => fn($fq) => $fq->with('lists')->orderBy('position'),
                    'listsWithoutFolder' => fn($lq) => $lq->orderBy('position'),
                    'statuses' => fn($sq) => $sq->orderBy('position'),
                ])->orderBy('position'),
                'members',
                'priorities',
                'labels',
            ]);
        }

        // Get user's tasks
        $myTasks = $user->getMyTasks();
        $overdueTasks = $user->getOverdueTasks();

        // Get running timer if any
        $runningTimer = TimeEntry::where('user_id' , $user->id)
            ->where('is_running', true)
            ->with('task.taskList.space')
            ->first();

        // Time stats
        $todayTimeSpent = $user->getTodayTimeSpent();
        $weekTimeSpent = $user->getWeekTimeSpent();

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces,
            'activeWorkspace' => $activeWorkspace,
            'myTasks' => $myTasks,
            'overdueTasks' => $overdueTasks,
            'runningTimer' => $runningTimer,
            'timeStats' => [
                'today' => $todayTimeSpent,
                'week' => $weekTimeSpent,
            ],
        ]);
    }

    /**
     * Switch active workspace.
     */
    public function switchWorkspace(Request $request, Workspace $workspace)
    {
        session(['active_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }
}
