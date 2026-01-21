<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TaskList;
use App\Models\Folder;
use App\Models\Space;
use App\Services\ListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ListController
 *
 * Handles List management .
 */
class ListController extends Controller
{
    private ListService $listService;

    public function __construct(ListService $listService)
    {
        $this->listService = $listService;
    }

    /**
     * Display the specified list.
     */
    public function show(Request $request, TaskList $list): Response
    {
        $this->authorize('view', $list->space);

        $list->load([
            'tasks' => function ($query) {
                $query->whereNull('parent_id')
                    ->with(['subtasks', 'status', 'assignees', 'labels', 'creator'])
                    ->orderBy('position');
            },
            'statuses' => function ($query) {
                $query->orderBy('position');
            },
        ]);

        return Inertia::render('Lists/Show', [
            'list' => $list,
            'space' => $list->space,
            'workspace' => $list->space->workspace,
        ]);
    }

    /**
     * Store a newly created list.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'folder_id' => 'nullable|exists:folders,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:1|max:4',
        ]);

        $space = Space::findOrFail($validated['space_id']);
        $this->authorize('update', $space);

        $this->listService->create($validated, $request->user());

        return redirect()->back()
            ->with('success', 'List created successfully.');
    }

    /**
     * Update the specified list.
     */
    public function update(Request $request, TaskList $list): RedirectResponse
    {
        $this->authorize('update', $list->space);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:1|max:4',
        ]);

        $this->listService->update($list, $validated);

        return redirect()->back()
            ->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified list.
     */
    public function destroy(TaskList $list): RedirectResponse
    {
        $this->authorize('update', $list->space);

        $this->listService->delete($list);

        return redirect()->back()
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Archive the specified list.
     */
    public function archive(TaskList $list): RedirectResponse
    {
        $this->authorize('update', $list->space);

        $this->listService->archive($list);

        return redirect()->back()
            ->with('success', 'List archived successfully.');
    }

    /**
     * Move list to different folder or space.
     */
    public function move(Request $request, TaskList $list): RedirectResponse
    {
        $this->authorize('update', $list->space);

        $validated = $request->validate([
            'space_id' => 'sometimes|exists:spaces,id',
            'folder_id' => 'nullable|exists:folders,id',
        ]);

        $newSpace = null;
        $newFolder = null;

        if (isset($validated['space_id'])) {
            $newSpace = Space::findOrFail($validated['space_id']);
            $this->authorize('update', $newSpace);
        }

        if (isset($validated['folder_id'])) {
            $newFolder = Folder::findOrFail($validated['folder_id']);
        }

        $this->listService->move($list, $newSpace, $newFolder);

        return redirect()->back()
            ->with('success', 'List moved successfully.');
    }

    /**
     * Reorder lists.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'folder_id' => 'nullable|exists:folders,id',
            'list_ids' => 'required|array',
            'list_ids.*' => 'exists:task_lists,id',
        ]);

        $space = Space::findOrFail($validated['space_id']);
        $this->authorize('update', $space);

        $folderId = $validated['folder_id'] ?? null;
        $this->listService->reorder($validated['list_ids'], $space->id, $folderId);

        return redirect()->back()
            ->with('success', 'List order updated.');
    }

    /**
     * Manage statuses for a list.
     */
    public function updateStatuses(Request $request, TaskList $list): RedirectResponse
    {
        $this->authorize('update', $list->space);

        $validated = $request->validate([
            'statuses' => 'required|array',
            'statuses.*.id' => 'nullable|exists:statuses,id',
            'statuses.*.name' => 'required|string|max:255',
            'statuses.*.color' => 'required|string|max:20',
            'statuses.*.type' => 'required|string|in:open,active,done,closed',
            'statuses.*.position' => 'required|integer|min:0',
        ]);

        $this->listService->updateStatuses($list, $validated['statuses']);

        return redirect()->back()
            ->with('success', 'Status updated successfully.');
    }
}
