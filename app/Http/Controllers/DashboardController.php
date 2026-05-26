<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TaskService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $workspaces = $this->workspaceService->getWorkspacesForUser($user);

        if ($workspaces->isEmpty()) {
            return redirect()->route('workspaces.index');
        }

        $activeWorkspaceId = session('active_workspace_id', $workspaces->first()?->id);
        $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId) ?? $workspaces->first();

        if ($activeWorkspace) {
            $wsRole = $this->accessService->getWorkspaceRole($user, $activeWorkspace);
            $isWsAdmin = in_array($wsRole, [AccessService::WORKSPACE_OWNER, AccessService::WORKSPACE_ADMIN], true);
            $listFilter = function ($q) use ($user, $isWsAdmin) {
                return $isWsAdmin ? $q : $q->whereHas('members', fn($mq) => $mq->where('user_id', $user->id));
            };

            $activeWorkspace->load([
                'spaces' => function ($q) use ($user, $isWsAdmin, $listFilter) {
                    if (!$isWsAdmin) {
                        $q->whereHas('members', fn($mq) => $mq->where('user_id', $user->id));
                    }
                    $q->with([
                        'folders' => fn($fq) => $fq->with(['projects' => $listFilter])->orderBy('position'),
                        'projectsWithoutFolder' => fn($lq) => $listFilter($lq)->orderBy('position'),
                        'statuses' => fn($sq) => $sq->orderBy('position'),
                    ])->orderBy('position');
                },
                'members',
                'labels',
            ]);
        }

        $mySubtasks = $this->taskService->getMySubtasks($user);
        $overdueSubtasks = $this->taskService->getMySubtasks($user, ['is_overdue' => true]);

        // Get running timer if any
        $runningTimer = TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->with('subtask.task.project.space')
            ->first();

        // Time stats
        $todayTimeSpent = $user->getTodayTimeSpent();
        $weekTimeSpent = $user->getWeekTimeSpent();
        $workdayStart = now()->copy()->setTime(config('business.workday_start_hour'), 0);
        $workdayEnd   = now()->copy()->setTime(config('business.workday_end_hour'), 0);
        $breakStart   = now()->copy()->setTime(config('business.break_start_hour'), 0);
        $breakEnd     = now()->copy()->setTime(config('business.break_end_hour'), 0);
        if (now()->lessThanOrEqualTo($workdayStart)) {
            $todayCapacity = 0;
        } else {
            $raw = $workdayStart->diffInMinutes(now()->min($workdayEnd));
            $breakElapsed = now()->greaterThan($breakStart)
                ? $breakStart->diffInMinutes(now()->min($breakEnd))
                : 0;
            $todayCapacity = max(0, $raw - $breakElapsed);
        }
        $weekCapacity = now()->startOfWeek()->diffInWeekdays(now()->endOfDay()->min(now())) * config('business.working_hours_per_day', 8) * 60;
        $todoCount = $mySubtasks->count();

        // Recent activity in the active workspace
        $recentActivity = $activeWorkspace
            ? Activity::where('workspace_id', $activeWorkspace->id)
                ->with('user')
                ->latest()
                ->limit(config('business.limits.recent_activity'))
                ->get()
                ->map(fn($a) => [
                    'id'           => $a->id,
                    'action'       => $a->action,
                    'description'  => $a->description,
                    'properties'   => $a->properties,
                    'created_at'   => $a->created_at,
                    'user' => $a->user ? [
                        'id'                => $a->user->id,
                        'name'              => $a->user->name,
                        'initials'          => $a->user->initials,
                        'avatar_color'      => $a->user->avatar_color,
                        'profile_photo_url' => $a->user->profile_photo_url,
                    ] : null,
                ])
            : [];

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces,
            'activeWorkspace' => $activeWorkspace,
            'mySubtasks' => $mySubtasks,
            'overdueSubtasks' => $overdueSubtasks,
            'runningTimer' => $runningTimer,
            'timeStats' => [
                'today' => $todayTimeSpent,
                'week' => $weekTimeSpent,
                'idle_today' => max(0, $todayCapacity - $todayTimeSpent),
                'idle_week' => max(0, $weekCapacity - $weekTimeSpent),
                'todo_count' => $todoCount,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Switch active workspace.
     */
    public function switchWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        session(['active_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->markNotificationsRead();

        return back();
    }
}
