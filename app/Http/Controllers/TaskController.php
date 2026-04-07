<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Label;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function store(StoreTaskRequest $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        try {
            $task = $this->taskService->create(
                $request->validated(),
                $list,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Task created successfully.',
                'task' => new TaskResource($task->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create task: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified task (Full detail view).
     */
    public function show(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): Response
    {
        abort_unless($this->accessService->canViewProject($request->user(), $list), 403);
        $task = $this->taskService->getTaskWithRelations($task);

        $workspace->load([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
            'members' => fn($q) => $q->select('users.id', 'users.name', 'users.email', 'users.profile_photo_path'),
            'labels' => fn($q) => $q->orderBy('name'),
        ]);

        return Inertia::render('Tasks/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
            'task' => new TaskResource($task),
            'statuses' => $space->statuses()->orderBy('position')->get(),
            'sprints' => $list->sprints()->orderBy('position')->get(),
        ]);
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        try {
            $updatedTask = $this->taskService->update($task, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Task updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update task: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        try {
            $this->taskService->delete($task, $request->user());

            return redirect()->route('lists.show', [$workspace->id, $space->id, $list->id])
                ->with('success', 'Task deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete task: ' . $e->getMessage()]);
        }
    }



    /**
     * Change task status.
     */
    public function changeStatus(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canOperateTasks($request->user(), $list), 403);
        $validated = $request->validate([
            'status_id' => 'required|exists:statuses,id',
        ]);

        try {
            $status = Status::findOrFail($validated['status_id']);
            $updatedTask = $this->taskService->changeStatus($task, $status, $request->user());

            return redirect()->back()->with([
                'success' => 'Task status updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to change status: ' . $e->getMessage()]);
        }
    }

    /**
     * Change task priority.
     */
    public function changePriority(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        $validated = $request->validate([
            'priority_level' => 'nullable|integer|in:1,2,3,4',
        ]);

        try {
            $updatedTask = $this->taskService->changePriority($task, $validated['priority_level'] ?? null, $request->user());

            return redirect()->back()->with([
                'success' => 'Task priority updated successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to change priority: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign user to task.
     */
    public function assign(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canAssignTasks($request->user(), $list), 403);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $updatedTask = $this->taskService->assign($task, $user, $request->user());

            return redirect()->back()->with([
                'success' => 'User assigned successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to assign user: ' . $e->getMessage()]);
        }
    }

    /**
     * Unassign user from task.
     */
    public function unassign(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canAssignTasks($request->user(), $list), 403);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $updatedTask = $this->taskService->unassign($task, $user, $request->user());

            return redirect()->back()->with([
                'success' => 'User unassigned successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to unassign user: ' . $e->getMessage()]);
        }
    }

    /**
     * Move task to different list.
     */
    public function move(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        $validated = $request->validate([
            'list_id' => 'required|exists:task_lists,id',
            'position' => 'nullable|integer|min:0',
        ]);

        try {
            $newList = TaskList::findOrFail($validated['list_id']);
            $updatedTask = $this->taskService->move($task, $newList, $request->user(), $validated['position'] ?? null);

            return redirect()->back()->with([
                'success' => 'Task moved successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to move task: ' . $e->getMessage()]);
        }
    }

    /**
     * Reorder tasks.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        try {
            $this->taskService->reorder($list, $request->validated('order'));

            return redirect()->back()->with('success', 'Tasks reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder tasks: ' . $e->getMessage()]);
        }
    }

    /**
     * Add label to task.
     */
    public function addLabel(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageLabels($request->user(), $list), 403);
        $validated = $request->validate([
            'label_id' => 'required|exists:labels,id',
        ]);

        try {
            $label = Label::findOrFail($validated['label_id']);
            $updatedTask = $this->taskService->addLabel($task, $label, $request->user());

            return redirect()->back()->with([
                'success' => 'Label added successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add label: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove label from task.
     */
    public function removeLabel(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageLabels($request->user(), $list), 403);
        $validated = $request->validate([
            'label_id' => 'required|exists:labels,id',
        ]);

        try {
            $label = Label::findOrFail($validated['label_id']);
            $updatedTask = $this->taskService->removeLabel($task, $label, $request->user());

            return redirect()->back()->with([
                'success' => 'Label removed successfully.',
                'task' => new TaskResource($updatedTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove label: ' . $e->getMessage()]);
        }
    }

    /**
     * Duplicate the task.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);
        try {
            $newTask = $this->taskService->duplicate($task, $request->user());

            return redirect()->back()->with([
                'success' => 'Task duplicated successfully.',
                'task' => new TaskResource($newTask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to duplicate task: ' . $e->getMessage()]);
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
            'type' => ['nullable', 'in:all,tasks,lists,spaces'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $validated['q'] ?? '';
        $type = $validated['type'] ?? 'all';
        $limit = $validated['limit'] ?? 20;
        $workspaceId = $validated['workspace_id'] ?? null;

        if (strlen($query) < 2) {
            return response()->json(['tasks' => [], 'lists' => [], 'spaces' => []]);
        }

        $query = str_replace(['%', '_'], ['\%', '\_'], $query);

        $user = $request->user();

        $tasksQuery = Task::whereHas('taskList.space.workspace', function ($q) use ($user, $workspaceId) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
                });
        })
            ->when($workspaceId, fn($q) => $q->whereHas('taskList.space', fn($q2) => $q2->where('workspace_id', $workspaceId)))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->when(!empty($validated['status_id']), fn($q) => $q->where('status_id', $validated['status_id']))
            ->when(!empty($validated['assignee_id']), fn($q) => $q->whereHas('assignees', fn($q2) => $q2->where('users.id', $validated['assignee_id'])))
            ->with(['taskList.space', 'status', 'assignees'])
            ->limit($limit);

        // Search tasks
        $tasks = $type === 'all' || $type === 'tasks' ? $tasksQuery->get() : collect();

        // Search lists
        $listsQuery = TaskList::whereHas('space.workspace', function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
                });
        })
            ->when($workspaceId, fn($q) => $q->whereHas('space', fn($q2) => $q2->where('workspace_id', $workspaceId)))
            ->where('name', 'like', "%{$query}%")
            ->with('space')
            ->limit(min($limit, 15));

        $lists = $type === 'all' || $type === 'lists' ? $listsQuery->get() : collect();

        // Search spaces
        $spacesQuery = Space::whereHas('workspace', function ($q) use ($user, $workspaceId) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
                });
        })
            ->when($workspaceId, fn($q) => $q->where('workspace_id', $workspaceId))
            ->where('name', 'like', "%{$query}%")
            ->with('workspace')
            ->limit(min($limit, 15));

        $spaces = $type === 'all' || $type === 'spaces' ? $spacesQuery->get() : collect();

        return response()->json([
            'tasks' => TaskResource::collection($tasks),
            'lists' => $lists,
            'spaces' => $spaces,
            'meta' => [
                'query' => $query,
                'type' => $type,
                'workspace_id' => $workspaceId,
                'count' => [
                    'tasks' => $tasks->count(),
                    'lists' => $lists->count(),
                    'spaces' => $spaces->count(),
                ],
            ],
        ]);
    }
}
