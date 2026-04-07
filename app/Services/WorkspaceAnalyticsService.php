<?php

namespace App\Services;

use App\Models\Space;
use App\Models\Sprint;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkspaceAnalyticsService
{
    public function getOverview(Workspace $workspace, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $tasksQuery = Task::query()
            ->whereHas('taskList.space', fn($q) => $q->where('workspace_id', $workspace->id));

        $subtasksQuery = Subtask::query()
            ->whereHas('task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id));

        $timeQuery = TimeEntry::query()
            ->whereHas('subtask.task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->whereBetween('started_at', [$start, $end]);

        $evm = $this->calculateEvm($workspace, $end);

        $completionTrend = collect(range(0, 13))->map(function ($offset) use ($workspace) {
            $day = now()->subDays(13 - $offset);
            $count = Subtask::whereHas('task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
                ->whereDate('completed_at', $day->toDateString())
                ->count();

            return [
                'date' => $day->toDateString(),
                'completed' => $count,
            ];
        });

        $throughputBySpace = Space::where('workspace_id', $workspace->id)
            ->withCount([
                'tasks as tasks_count',
                'tasks as archived_tasks_count' => fn($q) => $q->where('is_archived', true),
            ])
            ->get(['id', 'name'])
            ->map(fn($space) => [
                'id' => $space->id,
                'name' => $space->name,
                'tasks_count' => $space->tasks_count,
                'archived_tasks_count' => $space->archived_tasks_count,
            ]);

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'kpi' => [
                'tasks_total' => (clone $tasksQuery)->count(),
                'subtasks_completed' => (clone $subtasksQuery)
                    ->whereNotNull('completed_at')
                    ->whereBetween('completed_at', [$start, $end])
                    ->count(),
                'subtasks_overdue' => (clone $subtasksQuery)
                    ->whereNull('completed_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
                    ->count(),
                'active_sprints' => Sprint::whereHas('taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->where('is_active', true)
                    ->count(),
                'time_logged_minutes' => (clone $timeQuery)->sum('duration'),
            ],
            'evm' => $evm,
            'completion_trend' => $completionTrend,
            'throughput_by_space' => $throughputBySpace,
        ];
    }

    public function getCsvRows(Workspace $workspace, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $overview = $this->getOverview($workspace, $startDate, $endDate);

        return collect($overview['throughput_by_space'])->map(fn($row) => [
            'space' => $row['name'],
            'tasks_total' => $row['tasks_count'],
            'tasks_archived' => $row['archived_tasks_count'],
        ]);
    }

    protected function calculateEvm(Workspace $workspace, Carbon $asOf): array
    {
        $subtasks = Subtask::query()
            ->whereHas('task.taskList.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['assignees'])
            ->get();

        $pv = 0.0;
        $ev = 0.0;
        $ac = 0.0;

        foreach ($subtasks as $subtask) {
            $plannedMinutes = (float) ($subtask->planned_estimate ?? 0);
            if ($plannedMinutes <= 0) {
                continue;
            }

            $hourlyRate = (float) ($subtask->assignees->avg('hourly_rate') ?? 25.0);
            $plannedCost = ($plannedMinutes / 60) * $hourlyRate;
            $actualCost = (($subtask->time_spent ?? 0) / 60) * $hourlyRate;
            $progressRatio = $subtask->completed_at ? 1.0 : min(1, ($subtask->time_spent ?? 0) / max(1, $plannedMinutes));
            $scheduledProgress = $this->getScheduledProgressRatio($subtask, $asOf);

            $pv += $plannedCost * $scheduledProgress;
            $ev += $plannedCost * $progressRatio;
            $ac += $actualCost;
        }

        return [
            'pv' => round($pv, 2),
            'ev' => round($ev, 2),
            'ac' => round($ac, 2),
            'cv' => round($ev - $ac, 2),
            'sv' => round($ev - $pv, 2),
            'cpi' => $ac > 0 ? round($ev / $ac, 2) : null,
            'spi' => $pv > 0 ? round($ev / $pv, 2) : null,
        ];
    }

    protected function getScheduledProgressRatio(Subtask $subtask, Carbon $asOf): float
    {
        $start = $subtask->baseline_start_date ?? $subtask->start_date;
        $end = $subtask->baseline_due_date ?? $subtask->due_date;

        if (!$start || !$end) {
            return $subtask->completed_at ? 1.0 : 0.0;
        }

        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($asOf->lte($start)) {
            return 0.0;
        }

        if ($asOf->gte($end) || $end->equalTo($start)) {
            return 1.0;
        }

        $elapsed = $start->diffInMinutes($asOf);
        $total = max(1, $start->diffInMinutes($end));

        return min(1, max(0, $elapsed / $total));
    }
}
