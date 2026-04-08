<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TaskService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
        protected TaskService $taskService,
        protected AccessService $accessService,
    ) {}

    /**
     * Display the main dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $this->workspaceService->getWorkspacesForUser($user);

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
                'labels',
            ]);
        }

        $myTasks = $this->taskService->getMyTasks($user);
        $overdueTasks = $this->taskService->getMyTasks($user, ['is_overdue' => true]);

        // Get running timer if any
        $runningTimer = TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->with('subtask.task.taskList.space')
            ->first();

        // Time stats
        $todayTimeSpent = $user->getTodayTimeSpent();
        $weekTimeSpent = $user->getWeekTimeSpent();
        $workdayStart = now()->copy()->setTime(8, 0);
        $workdayEnd = now()->copy()->setTime(17, 0);
        $todayCapacity = now()->lessThanOrEqualTo($workdayStart)
            ? 0
            : $workdayStart->diffInMinutes(now()->min($workdayEnd));
        $weekCapacity = now()->startOfWeek()->diffInWeekdays(now()->endOfDay()->min(now())) * 8 * 60;
        $todoCount = collect($myTasks)->filter(fn($task) => ($task->subtasks ?? collect())->contains(fn($subtask) => is_null($subtask->completed_at)))->count();

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces,
            'activeWorkspace' => $activeWorkspace,
            'myTasks' => $myTasks,
            'overdueTasks' => $overdueTasks,
            'runningTimer' => $runningTimer,
            'timeStats' => [
                'today' => $todayTimeSpent,
                'week' => $weekTimeSpent,
                'idle_today' => max(0, $todayCapacity - $todayTimeSpent),
                'idle_week' => max(0, $weekCapacity - $weekTimeSpent),
                'todo_count' => $todoCount,
            ],
        ]);
    }

    /**
     * Switch active workspace.
     */
    public function switchWorkspace(Request $request, Workspace $workspace)
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        session(['active_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }

    public function markNotificationsRead(Request $request)
    {
        $request->user()->markNotificationsRead();

        return back()->with('success', 'Notifications marked as read.');
    }
}
