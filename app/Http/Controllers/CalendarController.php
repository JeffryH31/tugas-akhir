<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarFilterRequest;
use App\Models\Subtask;
use App\Models\Workspace;
use App\Services\AccessService;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(
        protected AccessService $accessService
    ) {}

    /**
     * Display the calendar view.
     */
    public function index(CalendarFilterRequest $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);
        $validated = $request->validated();

        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? now()->endOfMonth()->toDateString();
        $viewMode = $validated['view'] ?? 'month';

        $workspace->load([
            'spaces.statuses',
            'labels',
            'members',
        ]);

        $user = $request->user();
        $workspaceRole = $this->accessService->getWorkspaceRole($user, $workspace);

        $subtasks = Subtask::query()
            ->whereHas('task.taskList', function ($query) use ($workspace, $user, $workspaceRole) {
                $query->whereHas('space', function ($sq) use ($workspace, $user, $workspaceRole) {
                    $sq->where('workspace_id', $workspace->id);

                    // Non-admin users cannot see private spaces unless they're a space member
                    if ($workspaceRole !== 'admin') {
                        $sq->where(function ($pq) use ($user) {
                            $pq->where('is_private', false)
                               ->orWhereHas('members', fn($m) => $m->where('user_id', $user->id));
                        });
                    }
                });

                // Workspace admin can see all; others only see products they belong to
                if ($workspaceRole !== 'admin') {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('members', fn($m) => $m->where('user_id', $user->id))
                          ->orWhereDoesntHave('members'); // products without configured members
                    });
                }
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('due_date', [$startDate, $endDate])
                    ->orWhereBetween('start_date', [$startDate, $endDate]);
            })
            ->with([
                'status',
                'assignees',
                'labels',
                'activities' => fn($q) => $q->with('user')->latest()->limit(50),
                'task.taskList.space',
            ])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('Calendar/Index', [
            'workspace' => $workspace,
            'subtasks' => $subtasks,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'viewMode' => $viewMode,
        ]);
    }
}
