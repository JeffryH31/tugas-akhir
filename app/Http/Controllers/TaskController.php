<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TaskList;
use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TaskController 
 *
 * Handles tasks and subtasks using Inertia.js.
 * Updated for Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 */
class TaskController extends Controller
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task): Response
    {
        $this->authorize('view', $task->list->space);

        $task->load([
            'subtasks.assignee',
            'subtasks.status',
            'assignees',
            'labels',
            'status',
            'creator',
            'list.space.workspace',
            'comments.user',
            'attachments',
            'checklists.items',
            'timeEntries.user',
        ]);

        return Inertia::render('Tasks/Show', [
            'task' => $task,
            'list' => $task->list,
            'space' => $task->list->space,
            'workspace' => $task->list->space->workspace,
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:task_lists,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:urgent,high,normal,low'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $list = TaskList::findOrFail($validated['list_id']);
        $this->authorize('update', $list->space);

        $this->taskService->createTask($list, $request->user(), $validated);

        return back()->with('success', 'Task created successfully.');
    }

    /**
     * Store a subtask.
     */
    public function storeSubtask(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:urgent,high,normal,low'],
            'assignee_id' => ['nullable', 'exists:users,id'],
        ]);

        $this->authorize('update', $task->list->space);

        $this->taskService->createSubtask($task, $request->user(), $validated);

        return back()->with('success', 'Subtask created successfully.');
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:urgent,high,normal,low'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->authorize('update', $task->list->space);

        $this->taskService->updateTask($task, $validated);

        return back()->with('success', 'Task updated successfully.');
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('update', $task->list->space);

        $this->taskService->deleteTask($task);

        return back()->with('success', 'Task deleted successfully.');
    }

    /**
     * Update task status.
     */
    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'status_id' => ['required', 'exists:statuses,id'],
        ]);

        $this->authorize('update', $task->list->space);

        $this->taskService->changeStatus($task, $validated['status_id'], $request->user());

        return back()->with('success', 'Task status updated.');
    }

    /**
     * Move a task to another list.
     */
    public function move(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:task_lists,id'],
            'position' => ['nullable', 'integer', 'min:0'],

        ]);

        $this->authorize('update', $task->list->space);

        $targetList = TaskList::findOrFail($validated['list_id']);
        $this->authorize('update', $targetList->space);

        $this->taskService->moveToList($task, $targetList, $request->user(), $validated['position'] ?? null);

        return back()->with('success', 'Task moved successfully.');
    }

    /**
     * Sync task assignees.
     */
    public function syncAssignees(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'assignee_ids' => ['array'],
            'assignee_ids.*' => ['exists:users,id'],
        ]);

        $this->authorize('update', $task->list->space);

        $task->assignees()->sync($validated['assignee_ids'] ?? []);

        return back()->with('success', 'Assignees updated successfully.');
    }

    /**
     * Sync task labels.
     */
    public function syncLabels(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'label_ids' => ['array'],
            'label_ids.*' => ['exists:labels,id'],
        ]);

        $this->authorize('update', $task->list->space);

        $task->labels()->sync($validated['label_ids'] ?? []);

        return back()->with('success', 'Labels updated successfully.');
    }

    /**
     * Reorder tasks within a list.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'exists:task_lists,id'],
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['exists:tasks,id'],
        ]);

        $list = TaskList::findOrFail($validated['list_id']);
        $this->authorize('update', $list->space);

        $this->taskService->reorderTasks($list, $validated['task_ids']);

        return back()->with('success', 'Task order updated.');
    }
}
