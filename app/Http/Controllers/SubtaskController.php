<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Resources\SubtaskResource;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\Workspace;
use App\Services\SubtaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function __construct(
        protected SubtaskService $subtaskService
    ) {}

    /**
     * Store a newly created subtask.
     */
    public function store(StoreSubtaskRequest $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        try {
            $subtask = $this->subtaskService->create(
                $request->validated(),
                $task,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Subtask created successfully.',
                'subtask' => new SubtaskResource($subtask->load(['status', 'priority', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create subtask: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified subtask.
     */
    public function update(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                'status_id' => ['sometimes', 'exists:statuses,id'],
                'priority_id' => ['nullable', 'exists:priorities,id'],
                'start_date' => ['nullable', 'date'],
                'due_date' => ['nullable', 'date'],
                'time_estimate' => ['nullable', 'integer', 'min:0'],
                'assignee_ids' => ['sometimes', 'array'],
                'label_ids' => ['sometimes', 'array'],
            ]);

            $this->subtaskService->update($subtask, $validated, $request->user());

            return redirect()->back()->with('success', 'Subtask updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified subtask.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        try {
            $this->subtaskService->delete($subtask, $request->user());
            return redirect()->back()->with('success', 'Subtask deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder subtasks.
     */
    public function reorder(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task): RedirectResponse
    {
        $request->validate([
            'subtask_ids' => ['required', 'array'],
            'subtask_ids.*' => ['integer', 'exists:subtasks,id'],
        ]);

        try {
            $this->subtaskService->reorder($task, $request->subtask_ids);
            return redirect()->back()->with('success', 'Subtasks reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder subtasks: ' . $e->getMessage()]);
        }
    }
}
