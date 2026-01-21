<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TimeTrackingService
 *
 * Handles all business logic related to time tracking and time entries.
 * Following the Single Responsibility Principle (SRP).
 */
class TimeTrackingService
{
    /**
     * @var ActivityService
     */
    private ActivityService $activityService;

    /**
     * Constructor.
     *
     * @param ActivityService $activityService
     */
    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Start timer for a task.
     *
     * @param Task $task
     * @param User $user
     * @return TimeEntry
     */
    public function startTimer(Task $task, User $user): TimeEntry
    {
        // Stop any existing running timer for this user
        $this->stopAllRunningTimers($user);

        // Create new running timer
        $timeEntry = TimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'is_running' => true,
            'entry_type' => 'timer',
        ]);

        // Update task status to working
        $task->update(['status' => 'working']);

        // Log activity
        $this->activityService->logTimerStarted($user, $task);

        return $timeEntry;
    }

    /**
     * Stop timer for a task.
     *
     * @param TimeEntry $timeEntry
     * @param User $user
     * @return TimeEntry
     */
    public function stopTimer(TimeEntry $timeEntry, User $user): TimeEntry
    {
        if (!$timeEntry->is_running) {
            return $timeEntry;
        }

        $startedAt = Carbon::parse($timeEntry->started_at);
        $stoppedAt = now();
        $minutes = (int) $startedAt->diffInMinutes($stoppedAt);

        $timeEntry->update([
            'stopped_at' => $stoppedAt,
            'duration_minutes' => $minutes,
            'is_running' => false,
        ]);

        // Update task actual hours
        $this->syncTaskActualHours($timeEntry->task);

        // Log activity
        $this->activityService->logTimerStopped($user, $timeEntry->task, $minutes);

        return $timeEntry->fresh();
    }

    /**
     * Pause timer (set task to on hold).
     *
     * @param Task $task
     * @param User $user
     * @return TimeEntry|null
     */
    public function pauseTimer(Task $task, User $user): ?TimeEntry
    {
        // Find running timer for this task
        $runningTimer = TimeEntry::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->first();

        if ($runningTimer) {
            $this->stopTimer($runningTimer, $user);
        }

        // Update task status to on_hold
        $task->update(['status' => 'on_hold']);

        return $runningTimer;
    }

    /**
     * Resume timer (continue from on hold).
     *
     * @param Task $task
     * @param User $user
     * @return TimeEntry
     */
    public function resumeTimer(Task $task, User $user): TimeEntry
    {
        return $this->startTimer($task, $user);
    }

    /**
     * Complete task and stop any running timers.
     *
     * @param Task $task
     * @param User $user
     * @return Task
     */
    public function completeTask(Task $task, User $user): Task
    {
        // Stop any running timer for this task
        $runningTimer = TimeEntry::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->first();

        if ($runningTimer) {
            $this->stopTimer($runningTimer, $user);
        }

        // Mark task as completed
        $task->markAsCompleted();

        // Log activity
        $this->activityService->logTaskCompleted($user, $task);

        return $task->fresh();
    }

    /**
     * Log manual time entry.
     *
     * @param Task $task
     * @param User $user
     * @param array{duration_minutes: int, description?: string, logged_date?: string} $data
     * @return TimeEntry
     */
    public function logManualTime(Task $task, User $user, array $data): TimeEntry
    {
        $timeEntry = TimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'duration_minutes' => $data['duration_minutes'],
            'description' => $data['description'] ?? null,
            'entry_type' => 'manual',
            'logged_date' => $data['logged_date'] ?? now()->toDateString(),
            'is_running' => false,
        ]);

        // Update task actual hours
        $this->syncTaskActualHours($task);

        // Log activity
        $this->activityService->logManualTimeLogged($user, $task, $data['duration_minutes']);

        return $timeEntry;
    }

    /**
     * Update a time entry.
     *
     * @param TimeEntry $timeEntry
     * @param array{duration_minutes?: int, description?: string} $data
     * @return TimeEntry
     */
    public function updateTimeEntry(TimeEntry $timeEntry, array $data): TimeEntry
    {
        $timeEntry->update([
            'duration_minutes' => $data['duration_minutes'] ?? $timeEntry->duration_minutes,
            'description' => $data['description'] ?? $timeEntry->description,
        ]);

        // Update task actual hours
        $this->syncTaskActualHours($timeEntry->task);

        return $timeEntry->fresh();
    }

    /**
     * Delete a time entry.
     *
     * @param TimeEntry $timeEntry
     * @return bool
     */
    public function deleteTimeEntry(TimeEntry $timeEntry): bool
    {
        $task = $timeEntry->task;
        $result = $timeEntry->delete();

        // Update task actual hours
        if ($task) {
            $this->syncTaskActualHours($task);
        }

        return $result;
    }

    /**
     * Stop all running timers for a user.
     *
     * @param User $user
     * @return int Number of timers stopped
     */
    public function stopAllRunningTimers(User $user): int
    {
        $runningTimers = TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->get();

        foreach ($runningTimers as $timer) {
            $this->stopTimer($timer, $user);
        }

        return $runningTimers->count();
    }

    /**
     * Get the currently running timer for a user.
     *
     * @param User $user
     * @return TimeEntry|null
     */
    public function getRunningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::with(['task.list.space'])
            ->where('user_id', $user->id)
            ->where('is_running', true)
            ->first();
    }

    /**
     * Sync task actual hours from time entries.
     *
     * @param Task $task
     * @return void
     */
    private function syncTaskActualHours(Task $task): void
    {
        $totalMinutes = $task->timeEntries()
            ->where('is_running', false)
            ->sum('duration_minutes');

        $task->update([
            'actual_hours' => round($totalMinutes / 60, 2),
        ]);
    }

    /**
     * Get time entries for a task.
     *
     * @param Task $task
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeEntry>
     */
    public function getTimeEntriesForTask(Task $task)
    {
        return $task->timeEntries()
            ->with('user')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get time entries for a user within a date range.
     *
     * @param User $user
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Collection<int, TimeEntry>
     */
    public function getTimeEntriesForUser(User $user, Carbon $startDate, Carbon $endDate)
    {
        return TimeEntry::with(['task.list.space'])
            ->where('user_id', $user->id)
            ->where('is_running', false)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('logged_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('started_at', [$startDate, $endDate]);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get total tracked time for a task.
     *
     * @param Task $task
     * @return array{total_minutes: int, total_hours: float}
     */
    public function getTaskTrackedTime(Task $task): array
    {
        $totalMinutes = $task->timeEntries()
            ->where('is_running', false)
            ->sum('duration_minutes');

        return [
            'total_minutes' => (int) $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
        ];
    }

    /**
     * Get time summary for a user.
     *
     * @param User $user
     * @param string $period 'today', 'week', 'month'
     * @return array{total_minutes: int, total_hours: float, entry_count: int}
     */
    public function getUserTimeSummary(User $user, string $period = 'today'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $query = TimeEntry::where('user_id', $user->id)
            ->where('is_running', false)
            ->where('created_at', '>=', $startDate);

        $totalMinutes = $query->sum('duration_minutes');
        $entryCount = $query->count();

        return [
            'total_minutes' => (int) $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'entry_count' => $entryCount,
        ];
    }

    /**
     * Get elapsed time for running timer.
     *
     * @param TimeEntry $timeEntry
     * @return int Elapsed time in seconds
     */
    public function getElapsedTime(TimeEntry $timeEntry): int
    {
        if (!$timeEntry->is_running || !$timeEntry->started_at) {
            return 0;
        }

        return (int) Carbon::parse($timeEntry->started_at)->diffInSeconds(now());
    }
}
