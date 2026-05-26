<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Provide workspace-wide KPIs, EVM metrics, and analytics exports.
 */
class WorkspaceAnalyticsService
{
    /**
     * Build overview analytics payload for dashboards.
     *
     * @param Workspace $workspace
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array<string, mixed>
     */
    public function getOverview(Workspace $workspace, ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $tasksQuery = Task::query()
            ->whereHas('project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->whereBetween('created_at', [$start, $end]);

        $subtasksQuery = Subtask::query()
            ->whereHas('task.project.space', fn($q) => $q->where('workspace_id', $workspace->id));

        $timeQuery = TimeEntry::query()
            ->whereHas('subtask.task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->whereBetween('started_at', [$start, $end]);

        $evm = $this->calculateEvm($workspace, $end);

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
                    ->whereBetween('due_date', [$start, $end])
                    ->where('due_date', '<', now())
                    ->count(),
                'active_sprints' => Sprint::whereHas('project.space', fn($q) => $q->where('workspace_id', $workspace->id))
                    ->where('is_active', true)
                    ->where('start_date', '<=', $end)
                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
                    ->count(),
                'time_logged_minutes' => (clone $timeQuery)->sum('duration'),
            ],
            'evm' => $evm,
        ];
    }

    /**
     * Get flat rows suitable for CSV export.
     *
     * @param Workspace $workspace
     * @param string|null $startDate
     * @param string|null $endDate
     * @return Collection<int, array<string, mixed>>
     */
    public function getCsvRows(Workspace $workspace, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $overview = $this->getOverview($workspace, $startDate, $endDate);

        $rows = collect();

        foreach ($overview['kpi'] as $metric => $value) {
            $rows->push(['metric' => $metric, 'value' => $value]);
        }

        foreach ($overview['evm'] as $metric => $value) {
            $rows->push(['metric' => 'evm_' . $metric, 'value' => $value]);
        }

        return $rows;
    }

    /**
     * Calculate earned value management metrics as of a date.
     *
     * @param Workspace $workspace
     * @param Carbon $asOf
     * @return array<string, float|int|null>
     */
    protected function calculateEvm(Workspace $workspace, Carbon $asOf): array
    {
        $subtasks = Subtask::query()
            ->whereHas('task.project.space', fn($q) => $q->where('workspace_id', $workspace->id))
            ->with(['assignees'])
            ->get();

        $pv = 0.0;
        $ev = 0.0;
        $ac = 0.0;

        foreach ($subtasks as $subtask) {
            $plannedMinutes = (float) ($subtask->time_estimate ?? 0);
            if ($plannedMinutes <= 0) {
                continue;
            }

            $hourlyRate = (float) ($subtask->assignees->avg('hourly_rate') ?? config('business.default_hourly_rate', 150000));
            $plannedCost = ($plannedMinutes / 60) * $hourlyRate;
            $actualCost = (($subtask->time_spent ?? 0) / 60) * $hourlyRate;
            $progressRatio = $subtask->completed_at ? 1.0 : (($subtask->progress ?? 0) / 100);
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

    /**
     * Estimate scheduled progress ratio from baseline/current date ranges.
     *
     * @param Subtask $subtask
     * @param Carbon $asOf
     * @return float
     */
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
