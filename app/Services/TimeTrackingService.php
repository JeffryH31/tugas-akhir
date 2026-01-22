<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimeTrackingService
{
    /**
     * Log time entry for a task
     */
    public function logTime(Task $task, User $user, array $data): TimeEntry
    {
        return DB::transaction(function () use ($task, $user, $data) {
            $entry = TimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'duration' => $data['duration'], // in minutes
                'description' => $data['description'] ?? null,
                'started_at' => $data['started_at'] ?? now(),
                'ended_at' => $data['ended_at'] ?? now()->addMinutes($data['duration']),
                'is_billable' => $data['is_billable'] ?? false,
            ]);

            Activity::log($task->taskList->space->workspace, $user, $task, 'time_logged', [
                'name' => $task->name,
                'duration' => $entry->duration,
                'duration_formatted' => $entry->duration_formatted,
            ]);

            return $entry;
        });
    }

    /**
     * Start timer for a task
     */
    public function startTimer(Task $task, User $user, ?string $description = null): TimeEntry
    {
        $entry = TimeEntry::startTimer($task, $user, $description);

        Activity::log($task->taskList->space->workspace, $user, $task, 'timer_started', [
            'name' => $task->name,
        ]);

        return $entry;
    }

    /**
     * Stop running timer
     */
    public function stopTimer(TimeEntry $entry, User $user): TimeEntry
    {
        $entry->stop();

        Activity::log($entry->task->taskList->space->workspace, $user, $entry->task, 'timer_stopped', [
            'name' => $entry->task->name,
            'duration' => $entry->duration,
            'duration_formatted' => $entry->duration_formatted,
        ]);

        return $entry->fresh();
    }

    /**
     * Get running timer for user
     */
    public function getRunningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->with('task.taskList.space')
            ->first();
    }

    /**
     * Update a time entry
     */
    public function updateEntry(TimeEntry $entry, array $data, User $user): TimeEntry
    {
        $oldDuration = $entry->duration;

        $entry->update([
            'duration' => $data['duration'] ?? $entry->duration,
            'description' => $data['description'] ?? $entry->description,
            'started_at' => $data['started_at'] ?? $entry->started_at,
            'ended_at' => $data['ended_at'] ?? $entry->ended_at,
            'is_billable' => $data['is_billable'] ?? $entry->is_billable,
        ]);

        if ($oldDuration !== $entry->duration) {
            Activity::log($entry->task->taskList->space->workspace, $user, $entry->task, 'time_updated', [
                'name' => $entry->task->name,
            ], [
                'duration' => ['old' => $oldDuration, 'new' => $entry->duration],
            ]);
        }

        return $entry->fresh();
    }

    /**
     * Delete a time entry
     */
    public function deleteEntry(TimeEntry $entry, User $user): void
    {
        Activity::log($entry->task->taskList->space->workspace, $user, $entry->task, 'time_deleted', [
            'name' => $entry->task->name,
            'duration' => $entry->duration,
        ]);

        $entry->delete();
    }

    /**
     * Get time entries for a task
     */
    public function getEntriesForTask(Task $task): Collection
    {
        return $task->timeEntries()
            ->with('user')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get time entries for a user
     */
    public function getEntriesForUser(User $user, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = $user->timeEntries()
            ->with(['task.taskList.space'])
            ->orderBy('started_at', 'desc');

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get time summary for a task
     */
    public function getTaskTimeSummary(Task $task): array
    {
        $entries = $task->timeEntries;

        return [
            'total_minutes' => $entries->sum('duration'),
            'total_formatted' => $this->formatMinutes($entries->sum('duration')),
            'estimated_minutes' => $task->time_estimate,
            'estimated_formatted' => $task->time_estimate_formatted,
            'remaining_minutes' => max(0, ($task->time_estimate ?? 0) - $entries->sum('duration')),
            'progress' => $task->time_estimate > 0
                ? min(100, round(($entries->sum('duration') / $task->time_estimate) * 100, 1))
                : 0,
            'entries_count' => $entries->count(),
            'billable_minutes' => $entries->where('is_billable', true)->sum('duration'),
        ];
    }

    /**
     * Get user time summary
     */
    public function getUserTimeSummary(User $user, string $period = 'week'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };

        $entries = $user->timeEntries()
            ->where('started_at', '>=', $startDate)
            ->with('task')
            ->get();

        $byTask = $entries->groupBy('task_id')->map(fn($taskEntries) => [
            'task' => $taskEntries->first()->task,
            'total_minutes' => $taskEntries->sum('duration'),
        ])->sortByDesc('total_minutes')->values();

        $byDay = $entries->groupBy(fn($e) => $e->started_at->format('Y-m-d'))
            ->map(fn($dayEntries) => [
                'date' => $dayEntries->first()->started_at->format('Y-m-d'),
                'total_minutes' => $dayEntries->sum('duration'),
            ])->values();

        return [
            'total_minutes' => $entries->sum('duration'),
            'total_formatted' => $this->formatMinutes($entries->sum('duration')),
            'billable_minutes' => $entries->where('is_billable', true)->sum('duration'),
            'entries_count' => $entries->count(),
            'by_task' => $byTask->take(10),
            'by_day' => $byDay,
        ];
    }

    /**
     * Get workspace time report
     */
    public function getWorkspaceTimeReport(
        Workspace $workspace,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = TimeEntry::query()
            ->join('tasks', 'time_entries.task_id', '=', 'tasks.id')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->join('spaces', 'task_lists.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id);

        if ($startDate) {
            $query->where('time_entries.started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('time_entries.started_at', '<=', $endDate);
        }

        $byUser = DB::table('time_entries')
            ->join('tasks', 'time_entries.task_id', '=', 'tasks.id')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->join('spaces', 'task_lists.space_id', '=', 'spaces.id')
            ->join('users', 'time_entries.user_id', '=', 'users.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->when($startDate, fn($q) => $q->where('time_entries.started_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('time_entries.started_at', '<=', $endDate))
            ->select('users.id', 'users.name', DB::raw('SUM(time_entries.duration) as total_minutes'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_minutes')
            ->get();

        $bySpace = DB::table('time_entries')
            ->join('tasks', 'time_entries.task_id', '=', 'tasks.id')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->join('spaces', 'task_lists.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->when($startDate, fn($q) => $q->where('time_entries.started_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('time_entries.started_at', '<=', $endDate))
            ->select('spaces.id', 'spaces.name', DB::raw('SUM(time_entries.duration) as total_minutes'))
            ->groupBy('spaces.id', 'spaces.name')
            ->orderByDesc('total_minutes')
            ->get();

        $totalMinutes = $query->sum('time_entries.duration');

        return [
            'total_minutes' => $totalMinutes,
            'total_formatted' => $this->formatMinutes($totalMinutes),
            'by_user' => $byUser,
            'by_space' => $bySpace,
        ];
    }

    /**
     * Format minutes to human readable string
     */
    protected function formatMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $mins . 'm';
        }

        return $mins . 'm';
    }
}
