<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $task = $this->taskService->create(
                $request->validated(),
                $project,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Task created successfully.',
                'task' => new TaskResource($task->load(['status'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create task: '.$e->getMessage()]);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $updatedTask = $this->taskService->update($task, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Task updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update task: '.$e->getMessage()]);
        }
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $this->taskService->delete($task, $request->user());

            return redirect()->route('projects.show', [$workspace->id, $space->id, $project->id])
                ->with('success', 'Task deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete task: '.$e->getMessage()]);
        }
    }

    /**
     * Change task status.
     */
    public function changeStatus(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canOperateTasks($request->user(), $project), 403);
        $validated = $request->validate([
            'status_id' => [
                'required',
                Rule::exists('statuses', 'id')->where(function ($query) use ($space) {
                    $query->where('space_id', $space->id)
                        ->whereIn('applies_to', ['tasks', 'both']);
                }),
            ],
        ]);

        try {
            $status = Status::where('space_id', $space->id)->findOrFail($validated['status_id']);
            $updatedTask = $this->taskService->changeStatus($task, $status, $request->user());

            return redirect()->back()->with([
                'success' => 'Task status updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to change status: '.$e->getMessage()]);
        }
    }

    /**
     * Change task priority.
     */
    public function changePriority(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canOperateTasks($request->user(), $project), 403);
        $validated = $request->validate([
            'priority_level' => 'nullable|integer|in:1,2,3,4',
        ]);

        try {
            $updatedTask = $this->taskService->changePriority($task, $validated['priority_level'] ?? null, $request->user());

            return redirect()->back()->with([
                'success' => 'Task priority updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to change priority: '.$e->getMessage()]);
        }
    }

    /**
     * Assign user to task — intentionally disabled.
     *
     * Task-level assignment is not supported; assignees are managed at the
     * subtask level. This endpoint exists to return a helpful error message
     * if the route is ever hit.
     */
    public function assign(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canAssignTasks($request->user(), $project), 403);

        return redirect()->back()->withErrors([
            'error' => 'Task assignees are managed through subtasks and cannot be assigned directly.',
        ]);
    }

    /**
     * Unassign user from task.
     */
    public function unassign(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canAssignTasks($request->user(), $project), 403);

        return redirect()->back()->withErrors([
            'error' => 'Task assignees are managed through subtasks and cannot be removed directly.',
        ]);
    }

    /**
     * Move task to different list.
     */
    public function move(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        $validated = $request->validate([
            'list_id' => [
                'required',
                Rule::exists('projects', 'id')->where(fn ($query) => $query->where('space_id', $space->id)),
            ],
            'position' => 'nullable|integer|min:0',
        ]);

        try {
            $newList = Project::where('space_id', $space->id)->findOrFail($validated['list_id']);
            abort_unless($this->accessService->canManageTaskStructure($request->user(), $newList), 403);
            $updatedTask = $this->taskService->move($task, $newList, $request->user(), $validated['position'] ?? null);

            return redirect()->back()->with([
                'success' => 'Task moved successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to move task: '.$e->getMessage()]);
        }
    }

    /**
     * Reorder tasks.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $this->taskService->reorder($project, $request->validated('order'));

            return redirect()->back()->with('success', 'Tasks reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder tasks: '.$e->getMessage()]);
        }
    }

    /**
     * Add label to task.
     */
    public function addLabel(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageLabels($request->user(), $project), 403);
        $validated = $request->validate([
            'label_id' => [
                'required',
                Rule::exists('labels', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ]);

        try {
            $label = Label::where('workspace_id', $workspace->id)->findOrFail($validated['label_id']);
            $updatedTask = $this->taskService->addLabel($task, $label, $request->user());

            return redirect()->back()->with([
                'success' => 'Label added successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add label: '.$e->getMessage()]);
        }
    }

    /**
     * Remove label from task.
     */
    public function removeLabel(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageLabels($request->user(), $project), 403);
        $validated = $request->validate([
            'label_id' => [
                'required',
                Rule::exists('labels', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ]);

        try {
            $label = Label::where('workspace_id', $workspace->id)->findOrFail($validated['label_id']);
            $updatedTask = $this->taskService->removeLabel($task, $label, $request->user());

            return redirect()->back()->with([
                'success' => 'Label removed successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove label: '.$e->getMessage()]);
        }
    }

    /**
     * Duplicate the task.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $newTask = $this->taskService->duplicate($task, $request->user());

            return redirect()->back()->with([
                'success' => 'Task duplicated successfully.',
                'task' => new TaskResource($newTask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to duplicate task: '.$e->getMessage()]);
        }
    }

    /**
     * Get user's assigned tasks.
     */
    public function myTasks(Request $request): Response
    {
        $validated = $request->validate([
            'status_ids' => ['nullable', 'array'],
            'status_ids.*' => ['integer', 'exists:statuses,id'],
            'priority_levels' => ['nullable', 'array'],
            'priority_levels.*' => ['integer', 'in:1,2,3,4'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $tasks = $this->taskService->getMyTasks($request->user(), $validated);

        return Inertia::render('Tasks/MyTasks', [
            'tasks' => TaskResource::collection($tasks),
            'filters' => $validated,
        ]);
    }

    /**
     * Global search for tasks.
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
            'status_id' => ['nullable', 'integer', 'exists:statuses,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:all,tasks,projects,spaces'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->taskService->globalSearch($request->user(), $validated);

        return response()->json([
            'tasks' => TaskResource::collection($results['tasks']),
            'projects' => $results['projects'],
            'spaces' => $results['spaces'],
            'meta' => $results['meta'],
        ]);
    }
}
