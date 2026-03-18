<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarFilterRequest;
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

        $subtasks = \App\Models\Subtask::query()
            ->whereHas('task.taskList.space', function ($query) use ($workspace) {
                $query->where('workspace_id', $workspace->id);
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
