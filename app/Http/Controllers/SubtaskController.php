<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Requests\UpdateSubtaskRequest;
use App\Http\Resources\SubtaskResource;
use App\Models\Label;
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
                'subtask' => new SubtaskResource($subtask->load(['status', 'assignees', 'labels']))
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create subtask: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified subtask.
     */
    public function update(UpdateSubtaskRequest $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        try {
            $this->subtaskService->update($subtask, $request->validated(), $request->user());

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
     * Mark subtask as completed.
     */
    public function complete(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        if ($subtask->hasUncompletedDependencies()) {
            $names = $subtask->getUncompletedDependencyNames();
            $nameList = implode(', ', $names);
            return redirect()->back()->withErrors([
                'dependency' => "Cannot complete this subtask. It depends on uncompleted subtasks: {$nameList}"
            ]);
        }

        $subtask->markAsCompleted($request->user());

        return redirect()->back()->with('success', 'Subtask completed.');
    }

    /**
     * Reopen a completed subtask.
     */
    public function reopen(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        $subtask->markAsIncomplete();

        return redirect()->back()->with('success', 'Subtask reopened.');
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

    /**
     * Add label to subtask.
     */
    public function addLabel(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        $validated = $request->validate([
            'label_id' => 'required|exists:labels,id',
        ]);

        try {
            $label = Label::findOrFail($validated['label_id']);
            $subtask->labels()->syncWithoutDetaching([$label->id]);

            return redirect()->back()->with('success', 'Label added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add label: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove label from subtask.
     */
    public function removeLabel(Request $request, Workspace $workspace, Space $space, TaskList $list, Task $task, Subtask $subtask): RedirectResponse
    {
        $validated = $request->validate([
            'label_id' => 'required|exists:labels,id',
        ]);

        try {
            $label = Label::findOrFail($validated['label_id']);
            $subtask->labels()->detach($label->id);

            return redirect()->back()->with('success', 'Label removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove label: ' . $e->getMessage()]);
        }
    }
}
