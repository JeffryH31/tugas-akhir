<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprintRequest;
use App\Http\Requests\UpdateSprintRequest;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Workspace;
use App\Services\SprintService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SprintController extends Controller
{
    protected $sprintService;

    public function __construct(SprintService $sprintService)
    {
        $this->sprintService = $sprintService;
    }

    /**
     * Display a listing of sprints.
     */
    public function index(Workspace $workspace, Space $space)
    {
        $sprints = $space->sprints()
            ->withCount('subtasks')
            ->orderBy('start_date', 'desc')
            ->get();

        // Prefer explicit active flag (Jira-like), fallback to date-range for legacy data.
        $activeSprint = $sprints->firstWhere('is_active', true);

        // Find active sprint based on current date
        $today = now()->startOfDay();
        if (!$activeSprint) {
            $activeSprint = $sprints->first(function ($sprint) use ($today) {
                $startDate = \Carbon\Carbon::parse($sprint->start_date)->startOfDay();
                $endDate = \Carbon\Carbon::parse($sprint->end_date)->endOfDay();
                return $today->between($startDate, $endDate);
            });
        }

        $statistics = $activeSprint 
            ? $this->sprintService->getSprintStatistics($activeSprint)
            : null;

        $velocity = $this->sprintService->calculateVelocity($space);

        return Inertia::render('Sprints/Index', [
            'workspace' => $workspace,
            'space' => $space,
            'sprints' => $sprints,
            'activeSprint' => $activeSprint,
            'statistics' => $statistics,
            'velocity' => $velocity,
        ]);
    }

    /**
     * Display the sprint board.
     */
    public function show(Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $sprint->load([
            'subtasks.status',
            'subtasks.assignees',
            'subtasks.labels',
            'subtasks.task'
        ]);

        // Get backlog subtasks (subtasks without sprint in this space)
        $backlogSubtasks = $this->sprintService->getBacklogSubtasks($space);

        $statistics = $this->sprintService->getSprintStatistics($sprint);
        $burndown = $this->sprintService->getBurndownData($sprint);

        return Inertia::render('Sprints/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'sprint' => $sprint,
            'backlogSubtasks' => $backlogSubtasks,
            'statistics' => $statistics,
            'burndown' => $burndown,
            'statuses' => $space->statuses,
            'labels' => $workspace->labels,
            'members' => $workspace->members,
        ]);
    }

    /**
     * Store a newly created sprint.
     */
    public function store(StoreSprintRequest $request, Workspace $workspace, Space $space)
    {
        $sprint = $this->sprintService->createSprint($space, $request->validated());

        return redirect()->back()->with('success', 'Sprint created successfully!');
    }

    /**
     * Update the specified sprint.
     */
    public function update(UpdateSprintRequest $request, Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $this->sprintService->updateSprint($sprint, $request->validated());

        return redirect()->back()->with('success', 'Sprint updated successfully!');
    }

    /**
     * Start sprint.
     */
    public function start(Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $this->sprintService->startSprint($sprint);

        return redirect()->back()->with('success', 'Sprint started!');
    }

    /**
     * Complete sprint.
     */
    public function complete(Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $this->sprintService->completeSprint($sprint);

        return redirect()->back()->with('success', 'Sprint completed!');
    }

    /**
     * Add subtask to sprint.
     */
    public function addTask(Request $request, Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $validated = $request->validate([
            'subtask_id' => 'required|exists:subtasks,id',
        ]);

        $this->sprintService->addSubtaskToSprint($sprint, $validated['subtask_id']);

        return redirect()->back()->with('success', 'Subtask added to sprint!');
    }

    /**
     * Remove subtask from sprint.
     */
    public function removeTask(Request $request, Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $validated = $request->validate([
            'subtask_id' => 'required|exists:subtasks,id',
        ]);

        $this->sprintService->removeSubtaskFromSprint($sprint, $validated['subtask_id']);

        return redirect()->back()->with('success', 'Subtask removed from sprint!');
    }

    /**
     * Remove the specified sprint.
     */
    public function destroy(Workspace $workspace, Space $space, Sprint $sprint)
    {
        abort_unless((int) $sprint->space_id === (int) $space->id, 404);

        $this->sprintService->deleteSprint($sprint);

        return redirect()->route('sprints.index', [$workspace->id, $space->id])
            ->with('success', 'Sprint deleted successfully!');
    }
}
