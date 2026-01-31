<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Display the calendar view.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());
        $viewMode = $request->get('view', 'month');

        // Load workspace with necessary relationships
        $workspace->load([
            'spaces.statuses',
            'priorities',
            'labels',
            'members',
        ]);

        // Get all subtasks within date range that have due_date or start_date
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
                'priority',
                'assignees',
                'labels',
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
