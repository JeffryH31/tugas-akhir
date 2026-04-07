<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprintRequest;
use App\Http\Requests\UpdateSprintRequest;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\TaskList;
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
    public function index(Request $request, Workspace $workspace, Space $space)
    {
        $products = $space->lists()
            ->select('id', 'name', 'space_id')
            ->orderBy('position')
            ->get();

        $selectedList = null;
        if ($products->isNotEmpty()) {
            $requestedListId = (int) $request->integer('list_id');
            $selectedList = $requestedListId
                ? $products->firstWhere('id', $requestedListId)
                : $products->first();
        }

        if (!$selectedList) {
            return redirect()
                ->route('spaces.show', [$workspace, $space])
                ->with('error', 'No product available for sprint view.');
        }

        return redirect()->route('lists.show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $selectedList,
            'view' => 'sprint',
        ]);
    }

    /**
     * Display the sprint board.
     */
    public function show(Workspace $workspace, Space $space, Sprint $sprint)
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $list = $sprint->taskList;
        if (!$list) {
            $list = TaskList::where('space_id', $space->id)->orderBy('position')->first();
        }

        $sprint->load([
            'subtasks.status',
            'subtasks.assignees',
            'subtasks.labels',
            'subtasks.task'
        ]);

        // Get backlog subtasks (subtasks without sprint in this product/list)
        $backlogSubtasks = $list ? $this->sprintService->getBacklogSubtasks($list) : collect();

        $statistics = $this->sprintService->getSprintStatistics($sprint);
        $burndown = $this->sprintService->getBurndownData($sprint);

        return Inertia::render('Sprints/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
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
        $validated = $request->validated();

        $list = TaskList::where('space_id', $space->id)->findOrFail((int) $validated['list_id']);
        $this->sprintService->createSprint($list, $validated);

        return redirect()->back()->with('success', 'Sprint created successfully!');
    }

    /**
     * Update the specified sprint.
     */
    public function update(UpdateSprintRequest $request, Workspace $workspace, Space $space, Sprint $sprint)
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $validated = $request->validated();
        if (isset($validated['list_id'])) {
            TaskList::where('space_id', $space->id)->findOrFail((int) $validated['list_id']);
        }

        $this->sprintService->updateSprint($sprint, $validated);

        return redirect()->back()->with('success', 'Sprint updated successfully!');
    }

    /**
     * Start sprint.
     */
    public function start(Workspace $workspace, Space $space, Sprint $sprint)
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $this->sprintService->startSprint($sprint);

        return redirect()->back()->with('success', 'Sprint started!');
    }

    /**
     * Complete sprint.
     */
    public function complete(Workspace $workspace, Space $space, Sprint $sprint)
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $this->sprintService->completeSprint($sprint);

        return redirect()->back()->with('success', 'Sprint completed!');
    }

    /**
     * Add subtask to sprint.
     */
    public function addTask(Request $request, Workspace $workspace, Space $space, Sprint $sprint)
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);

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
        $this->ensureSprintBelongsToSpace($sprint, $space);

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
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $this->sprintService->deleteSprint($sprint);

        return redirect()->back()->with('success', 'Sprint deleted successfully!');
    }

    private function ensureSprintBelongsToSpace(Sprint $sprint, Space $space): void
    {
        if ($sprint->task_list_id) {
            $belongs = TaskList::where('id', $sprint->task_list_id)
                ->where('space_id', $space->id)
                ->exists();
            abort_unless($belongs, 404);
            return;
        }

        abort_unless((int) $sprint->space_id === (int) $space->id, 404);
    }
}
