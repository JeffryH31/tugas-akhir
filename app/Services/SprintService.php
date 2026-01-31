<?php

namespace App\Services;

use App\Models\Space;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;

class SprintService
{
    /**
     * Create a new sprint.
     */
    public function createSprint(Space $space, array $data): Sprint
    {
        $position = $space->sprints()->max('position') + 1;

        return $space->sprints()->create([
            'name' => $data['name'],
            'goal' => $data['goal'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? false,
            'position' => $position,
        ]);
    }

    /**
     * Update sprint.
     */
    public function updateSprint(Sprint $sprint, array $data): Sprint
    {
        $sprint->update([
            'name' => $data['name'] ?? $sprint->name,
            'goal' => $data['goal'] ?? $sprint->goal,
            'start_date' => $data['start_date'] ?? $sprint->start_date,
            'end_date' => $data['end_date'] ?? $sprint->end_date,
            'is_active' => $data['is_active'] ?? $sprint->is_active,
        ]);

        return $sprint->fresh();
    }

    /**
     * Delete sprint.
     */
    public function deleteSprint(Sprint $sprint): bool
    {
        // Remove sprint from all subtasks
        $sprint->subtasks()->update(['sprint_id' => null]);
        
        return $sprint->delete();
    }

    /**
     * Start sprint (activate).
     */
    public function startSprint(Sprint $sprint): Sprint
    {
        // Deactivate other active sprints in the same space
        Sprint::where('space_id', $sprint->space_id)
            ->where('id', '!=', $sprint->id)
            ->update(['is_active' => false]);

        $sprint->update(['is_active' => true]);

        return $sprint->fresh();
    }

    /**
     * Complete sprint.
     */
    public function completeSprint(Sprint $sprint): Sprint
    {
        $sprint->update(['is_active' => false]);

        return $sprint->fresh();
    }

    /**
     * Move subtask to sprint.
     */
    public function addSubtaskToSprint(Sprint $sprint, int $subtaskId): void
    {
        DB::table('subtasks')
            ->where('id', $subtaskId)
            ->update(['sprint_id' => $sprint->id]);
    }

    /**
     * Remove subtask from sprint.
     */
    public function removeSubtaskFromSprint(int $subtaskId): void
    {
        DB::table('subtasks')
            ->where('id', $subtaskId)
            ->update(['sprint_id' => null]);
    }

    /**
     * Get sprint statistics.
     */
    public function getSprintStatistics(Sprint $sprint): array
    {
        $subtasks = $sprint->subtasks()->with('status')->get();

        $totalSubtasks = $subtasks->count();
        $completedSubtasks = $subtasks->filter(fn($subtask) => $subtask->completed_at !== null)->count();
        $inProgressSubtasks = $subtasks->filter(fn($subtask) => 
            $subtask->completed_at === null && 
            in_array($subtask->status?->type, ['in_progress', 'review'])
        )->count();
        
        $totalEstimate = $subtasks->sum('time_estimate'); // in minutes
        $totalSpent = $subtasks->sum('time_spent'); // in minutes
        
        $completionRate = $totalSubtasks > 0 ? round(($completedSubtasks / $totalSubtasks) * 100) : 0;

        return [
            'total_subtasks' => $totalSubtasks,
            'completed_subtasks' => $completedSubtasks,
            'in_progress_subtasks' => $inProgressSubtasks,
            'not_started_subtasks' => $totalSubtasks - $completedSubtasks - $inProgressSubtasks,
            'total_estimate' => $totalEstimate,
            'total_spent' => $totalSpent,
            'completion_rate' => $completionRate,
            'remaining_days' => $sprint->getRemainingDays(),
            'duration_days' => $sprint->getDurationInDays(),
        ];
    }

    /**
     * Get backlog subtasks (subtasks without sprint in the space).
     */
    public function getBacklogSubtasks(Space $space): \Illuminate\Support\Collection
    {
        return DB::table('subtasks')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('task_lists', 'tasks.task_list_id', '=', 'task_lists.id')
            ->where('task_lists.space_id', $space->id)
            ->whereNull('subtasks.sprint_id')
            ->whereNull('subtasks.deleted_at')
            ->select('subtasks.*')
            ->get();
    }

    /**
     * Calculate sprint velocity (completed subtasks per sprint).
     */
    public function calculateVelocity(Space $space, int $lastNSprints = 5): array
    {
        $sprints = Sprint::where('space_id', $space->id)
            ->where('end_date', '<', now())
            ->orderBy('end_date', 'desc')
            ->limit($lastNSprints)
            ->get();

        $velocityData = [];
        
        foreach ($sprints as $sprint) {
            $completedSubtasks = $sprint->subtasks()
                ->whereNotNull('completed_at')
                ->count();

            $totalTimeSpent = $sprint->subtasks()
                ->whereNotNull('completed_at')
                ->sum('time_spent');

            $velocityData[] = [
                'sprint_name' => $sprint->name,
                'completed_subtasks' => $completedSubtasks,
                'total_time_spent' => $totalTimeSpent, // in minutes
            ];
        }

        $averageVelocity = collect($velocityData)->avg('completed_subtasks');
        $averageTimeSpent = collect($velocityData)->avg('total_time_spent');

        return [
            'sprints' => $velocityData,
            'average_velocity' => round($averageVelocity, 1),
            'average_time_spent' => round($averageTimeSpent, 1),
        ];
    }

    /**
     * Get burndown data for sprint.
     */
    public function getBurndownData(Sprint $sprint): array
    {
        $totalSubtasks = $sprint->subtasks()->count();
        
        if ($totalSubtasks === 0) {
            return [];
        }

        $daysInSprint = $sprint->getDurationInDays();
        $idealBurndown = [];
        
        // Calculate ideal burndown line
        for ($day = 0; $day <= $daysInSprint; $day++) {
            $idealBurndown[] = [
                'day' => $day,
                'remaining' => $totalSubtasks - ($totalSubtasks * $day / $daysInSprint),
            ];
        }

        // Get actual completed subtasks by date
        $completedByDate = DB::table('subtasks')
            ->where('sprint_id', $sprint->id)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$sprint->start_date, $sprint->end_date])
            ->selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $actualBurndown = [];
        $remainingSubtasks = $totalSubtasks;
        
        for ($day = 0; $day <= $daysInSprint; $day++) {
            $currentDate = $sprint->start_date->copy()->addDays($day);
            $dateStr = $currentDate->format('Y-m-d');
            
            if (isset($completedByDate[$dateStr])) {
                $remainingSubtasks -= $completedByDate[$dateStr]->count;
            }
            
            $actualBurndown[] = [
                'day' => $day,
                'date' => $dateStr,
                'remaining' => max(0, $remainingSubtasks),
            ];
            
            // Stop at today for active sprints
            if ($currentDate->isToday() || $currentDate->isFuture()) {
                break;
            }
        }

        return [
            'ideal' => $idealBurndown,
            'actual' => $actualBurndown,
        ];
    }
}
