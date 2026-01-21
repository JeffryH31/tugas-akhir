<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Space;
use App\Services\ActivityService;
use App\Services\SpaceService;
use App\Services\TaskService;
use App\Services\TimeTrackingService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController 
 *
 * Handles the main dashboard view with Inertia.
 * Updated for Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 */
class DashboardController extends Controller
{
    private WorkspaceService $workspaceService;
    private SpaceService $spaceService;
    private TaskService $taskService;
    private TimeTrackingService $timeTrackingService;
    private ActivityService $activityService;

    public function __construct(
        WorkspaceService $workspaceService,
        SpaceService $spaceService,
        TaskService $taskService,
        TimeTrackingService $timeTrackingService,
        ActivityService $activityService
    ) {
        $this->workspaceService = $workspaceService;
        $this->spaceService = $spaceService;
        $this->taskService = $taskService;
        $this->timeTrackingService = $timeTrackingService;
        $this->activityService = $activityService;
    }

    /**
     * Display the main dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get all accessible workspaces with their spaces
        $workspaces = $this->workspaceService->getAccessibleWorkspaces($user);

        // Format workspaces for frontend (using 'boards' for frontend compatibility - these are Spaces)
        $formattedWorkspaces = $workspaces->map(fn($workspace) => [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'is_owner' => $workspace->owner_id === $user->id,
            'boards' => $workspace->spaces->map(fn($space) => [
                'id' => $space->id,
                'name' => $space->name,
                'color' => $space->color ?? '#6366F1',
                'starred' => $space->is_starred,
            ]),
        ]);

        // Get active space (from query param or first available)
        $activeSpaceId = $request->query('space');
        $activeSpace = null;

        if ($activeSpaceId) {
            $activeSpace = Space::with([
                'folders.lists.tasks',
                'lists.tasks.assignee',
                'lists.tasks.labels',
                'lists.statuses',
                'members',
            ])->find($activeSpaceId);

            if ($activeSpace) {
                $this->authorize('view', $activeSpace);
            }
        }

        // If no active space, use the first available
        if (!$activeSpace && $workspaces->isNotEmpty()) {
            $firstWorkspace = $workspaces->first();
            if ($firstWorkspace->spaces->isNotEmpty()) {
                $activeSpace = Space::with([
                    'folders.lists.tasks',
                    'lists.tasks.assignee',
                    'lists.tasks.labels',
                    'lists.statuses',
                    'members',
                ])->find($firstWorkspace->spaces->first()->id);
            }
        }

        // Format active space for frontend
        $formattedSpace = $activeSpace ? $this->formatSpaceForFrontend($activeSpace) : null;

        // Get running timer for user
        $runningTimer = $this->timeTrackingService->getRunningTimer($user);

        // Get recent activities
        $recentActivities = $this->activityService->getRecentActivities($user, 20);

        // Get my tasks (assigned to current user)
        $myTasks = $this->taskService->getTasksForUser($user);

        // Get time summary
        $timeSummary = [
            'today' => $this->timeTrackingService->getUserTimeSummary($user, 'today'),
            'week' => $this->timeTrackingService->getUserTimeSummary($user, 'week'),
        ];

        // Get all team members from active space's workspace
        $teamMembers = [];
        if ($activeSpace && $activeSpace->workspace) {
            $teamMembers = $activeSpace->workspace->members->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->initials,
                'color' => $this->getAvatarColor($member->id),
                'role' => $member->pivot->role ?? 'member',
            ])->values();
        }

        return Inertia::render('Dashboard', [
            'workspaces' => $formattedWorkspaces,
            'activeBoard' => $formattedSpace, // Using 'activeBoard' for frontend compatibility (this is a Space)
            'teamMembers' => $teamMembers,
            'runningTimer' => $runningTimer ? $this->formatTimeEntry($runningTimer) : null,
            'recentActivities' => $recentActivities->map(fn($activity) => [
                'id' => $activity->id,
                'action' => $activity->type,
                'description' => $activity->getDescriptionText(),
                'icon' => $activity->getIcon(),
                'color' => $activity->getColor(),
                'time_ago' => $activity->created_at?->diffForHumans(),
                'user' => [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'avatar' => $activity->user->initials,
                ],
            ]),
            'myTasks' => $myTasks->map(fn($task) => $this->formatTask($task)),
            'timeSummary' => $timeSummary,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->initials,
            ],
        ]);
    }

    /**
     * Display a specific space.
     */
    public function space(Request $request, Space $space): Response
    {
        $this->authorize('view', $space);

        $user = $request->user();

        // Load space with full data
        $space->load([
            'folders.lists.tasks.assignee',
            'folders.lists.tasks.labels',
            'folders.lists.statuses',
            'lists.tasks.assignee',
            'lists.tasks.labels',
            'lists.statuses',
            'members',
        ]);

        // Get all accessible workspaces
        $workspaces = $this->workspaceService->getAccessibleWorkspaces($user);

        // Format workspaces for frontend
        $formattedWorkspaces = $workspaces->map(fn($workspace) => [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'is_owner' => $workspace->owner_id === $user->id,
            'boards' => $workspace->spaces->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color ?? '#6366F1',
                'starred' => $s->is_starred,
            ]),
        ]);

        $formattedSpace = $this->formatSpaceForFrontend($space);

        // Get running timer
        $runningTimer = $this->timeTrackingService->getRunningTimer($user);

        // Get team members
        $teamMembers = $space->workspace->members->map(fn($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->initials,
            'color' => $this->getAvatarColor($member->id),
            'role' => $member->pivot->role ?? 'member',
        ])->values();

        // Get time summary
        $timeSummary = [
            'today' => $this->timeTrackingService->getUserTimeSummary($user, 'today'),
            'week' => $this->timeTrackingService->getUserTimeSummary($user, 'week'),
        ];

        return Inertia::render('Dashboard', [
            'workspaces' => $formattedWorkspaces,
            'activeBoard' => $formattedSpace, // Using 'activeBoard' for frontend compatibility
            'teamMembers' => $teamMembers,
            'runningTimer' => $runningTimer ? $this->formatTimeEntry($runningTimer) : null,
            'recentActivities' => [],
            'myTasks' => [],
            'timeSummary' => $timeSummary,
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->initials,
            ],
        ]);
    }

    /**
     * Format space data for frontend (using names compatible with frontend components).
     */
    private function formatSpaceForFrontend(Space $space): array
    {
        // Combine folders with their lists and standalone lists into featureLists for frontend
        $featureLists = collect();
        
        // Add folder lists
        foreach ($space->folders as $folder) {
            foreach ($folder->lists as $list) {
                $formattedList = $this->formatList($list);
                $formattedList['folder_id'] = $folder->id;
                $formattedList['folder_name'] = $folder->name;
                $featureLists->push($formattedList);
            }
        }
        
        // Add standalone lists (not in any folder)
        foreach ($space->lists->whereNull('folder_id') as $list) {
            $featureLists->push($this->formatList($list));
        }
        
        return [
            'id' => $space->id,
            'name' => $space->name,
            'description' => $space->description,
            'color' => $space->color ?? '#6366F1',
            'workspace_id' => $space->workspace_id,
            'is_private' => $space->is_private,
            'starred' => $space->is_starred,
            'members' => $space->members->pluck('id')->toArray(),
            'featureLists' => $featureLists->sortBy('position')->values()->toArray(),
            'folders' => $space->folders->map(fn($folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'color' => $folder->color,
                'position' => $folder->position,
            ]),
            'labels' => [], // TODO: add labels when implemented
        ];
    }

    /**
     * Format list data (using 'features' for tasks - frontend compatibility).
     */
    private function formatList($list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'description' => $list->description,
            'color' => $list->color,
            'position' => $list->position,
            'statuses' => $list->statuses->map(fn($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'type' => $status->type,
                'position' => $status->position,
            ]),
            'features' => $list->tasks->map(fn($task) => $this->formatTask($task)),
        ];
    }

    /**
     * Format task data.
     */
    private function formatTask($task): array
    {
        $primaryAssignee = $task->assignees->first();
        
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'status_id' => $task->status_id,
            'priority' => $task->priority ?? 'normal',
            'position' => $task->position,
            'assignee_id' => $primaryAssignee?->id,
            'assignee' => $primaryAssignee ? [
                'id' => $primaryAssignee->id,
                'name' => $primaryAssignee->name,
                'avatar' => $primaryAssignee->initials,
            ] : null,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'estimated_hours' => $task->estimated_hours ?? 0,
            'actual_hours' => $task->actual_hours ?? 0,
            'is_completed' => $task->is_completed,
            'labels' => $task->labels?->map(fn($label) => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ]) ?? [],
            'subtasks_count' => $task->subtasks?->count() ?? 0,
            'completed_subtasks_count' => $task->subtasks?->where('is_completed', true)->count() ?? 0,
        ];
    }

    /**
     * Format time entry data.
     */
    private function formatTimeEntry($entry): array
    {
        return [
            'id' => $entry->id,
            'task_id' => $entry->task_id,
            'user_id' => $entry->user_id,
            'hours' => round($entry->duration_minutes / 60, 2),
            'minutes' => $entry->duration_minutes,
            'date' => $entry->logged_date?->toDateString() ?? $entry->created_at?->toDateString(),
            'description' => $entry->description,
            'is_running' => $entry->is_running,
            'started_at' => $entry->started_at?->toISOString(),
        ];
    }

    /**
     * Get a consistent avatar color based on user ID.
     */
    private function getAvatarColor(int $userId): string
    {
        $colors = [
            '#6366F1', '#EC4899', '#10B981', '#F59E0B', '#0EA5E9',
            '#8B5CF6', '#EF4444', '#14B8A6', '#F97316', '#06B6D4',
        ];

        return $colors[$userId % count($colors)];
    }
}
