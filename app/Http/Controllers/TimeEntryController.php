<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\TimeTrackingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TimeTrackingService $timeTrackingService
    ) {}

    /**
     * Display time entries for user.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        
        $entries = $this->timeTrackingService->getEntriesForUser(
            $user,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        $runningTimer = $this->timeTrackingService->getRunningTimer($user);

        $workspaces = $user->workspaces()->with([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
        ])->get();
        
        $activeWorkspaceId = session('active_workspace_id', $workspaces->first()?->id);
        $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId) ?? $workspaces->first();

        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $stats = [
            'today' => TimeEntry::where('user_id', $user->id)
                ->whereDate('started_at', '>=', $today)
                ->sum('duration'),
            'week' => TimeEntry::where('user_id', $user->id)
                ->whereDate('started_at', '>=', $weekStart)
                ->sum('duration'),
            'month' => TimeEntry::where('user_id', $user->id)
                ->whereDate('started_at', '>=', $monthStart)
                ->sum('duration'),
            'billable' => TimeEntry::where('user_id', $user->id)
                ->where('is_billable', true)
                ->whereDate('started_at', '>=', $monthStart)
                ->sum('duration'),
        ];

        return Inertia::render('TimeTracking/Index', [
            'activeWorkspace' => $activeWorkspace,
            'entries' => $entries,
            'runningTimer' => $runningTimer,
            'stats' => $stats,
        ]);
    }

    /**
     * Log time entry.
     */
    public function store(StoreTimeEntryRequest $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        try {
            $entry = $this->timeTrackingService->logTime(
                $subtask,
                $request->user(),
                $request->validated()
            );

            return redirect()->back()->with([
                'success' => 'Time logged successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user'))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to log time: ' . $e->getMessage()]);
        }
    }

    /**
     * Start timer.
     */
    public function startTimer(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task)
    {
        $validated = $request->validate([
            'subtask_id' => ['nullable', 'integer', 'exists:subtasks,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $subtask = !empty($validated['subtask_id'])
                ? Subtask::where('id', $validated['subtask_id'])->where('task_id', $task->id)->firstOrFail()
                : Subtask::where('task_id', $task->id)->firstOrFail();

            $entry = $this->timeTrackingService->startTimer(
                $subtask,
                $request->user(),
                $validated['description'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Timer started successfully.',
                    'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user')),
                ]);
            }

            return redirect()->back()->with([
                'success' => 'Timer started successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user'))
            ]);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Failed to start timer: ' . $e->getMessage()]);
        }
    }

    /**
     * Stop timer.
     */
    public function stopTimer(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, TimeEntry $entry)
    {
        try {
            $stoppedEntry = $this->timeTrackingService->stopTimer($entry, $request->user());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Timer stopped successfully.',
                    'timeEntry' => new \App\Http\Resources\TimeEntryResource($stoppedEntry->load('user')),
                ]);
            }

            return redirect()->back()->with([
                'success' => 'Timer stopped successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($stoppedEntry->load('user'))
            ]);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['error' => 'Failed to stop timer: ' . $e->getMessage()]);
        }
    }

    /**
     * Get running timer.
     */
    public function runningTimer(Request $request)
    {
        $timer = $this->timeTrackingService->getRunningTimer($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'timer' => $timer ? new \App\Http\Resources\TimeEntryResource($timer->load('user', 'subtask')) : null,
            ]);
        }

        return Inertia::render('TimeTracking/RunningTimer', [
            'timer' => $timer ? new \App\Http\Resources\TimeEntryResource($timer->load('user', 'subtask')) : null,
        ]);
    }

    /**
     * Update time entry.
     */
    public function update(UpdateTimeEntryRequest $request, TimeEntry $entry): RedirectResponse
    {
        try {
            $this->authorize('update', $entry);
            
            $updatedEntry = $this->timeTrackingService->updateEntry($entry, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Time entry updated successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($updatedEntry->load('user'))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update time entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete time entry.
     */
    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        try {
            $this->authorize('delete', $entry);
            
            $this->timeTrackingService->deleteEntry($entry, $request->user());

            return redirect()->back()->with('success', 'Time entry deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete time entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Get workspace time report.
     */
    public function workspaceReport(Request $request, Workspace $workspace): Response
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $report = $this->timeTrackingService->getWorkspaceTimeReport(
            $workspace,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return Inertia::render('TimeTracking/WorkspaceReport', [
            'workspace' => $workspace,
            'report' => $report,
        ]);
    }
}
