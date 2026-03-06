<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Models\Space;
use App\Models\Status;
use App\Models\Workspace;
use App\Services\SpaceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SpaceService $spaceService
    ) {}

    /**
     * Store a newly created space.
     */
    public function store(StoreSpaceRequest $request, Workspace $workspace): RedirectResponse
    {
        try {
            $space = $this->spaceService->create(
                $request->validated(),
                $workspace,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Space created successfully.',
                'space' => $space
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create space: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified space.
     */
    public function show(Request $request, Workspace $workspace, Space $space): Response
    {
        $space = $this->spaceService->getWithHierarchy($space);
        $statistics = $this->spaceService->getStatistics($space);

        $workspace->load([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
            'members',
            'priorities',
            'labels',
        ]);

        return Inertia::render('Spaces/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Update the specified space.
     */
    public function update(UpdateSpaceRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        try {
            $updatedSpace = $this->spaceService->update($space, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Space updated successfully.',
                'space' => $updatedSpace
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update space: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified space.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        try {
            $this->spaceService->delete($space, $request->user());

            return redirect()
                ->route('workspaces.show', $workspace)
                ->with('success', 'Space deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete space: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle starred status.
     */
    public function toggleStar(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        try {
            $updatedSpace = $this->spaceService->toggleStar($space);

            return redirect()->back()->with([
                'success' => 'Space starred status updated.',
                'space' => $updatedSpace
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update space: ' . $e->getMessage()]);
        }
    }

    /**
     * Reorder spaces.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace): RedirectResponse
    {
        try {
            $this->spaceService->reorder($workspace, $request->order);

            return redirect()->back()->with('success', 'Spaces reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder spaces: ' . $e->getMessage()]);
        }
    }

    /**
     * Add status to space.
     */
    public function addStatus(StoreStatusRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        try {
            $status = $this->spaceService->addStatus($space, $request->validated());

            return redirect()->back()->with([
                'success' => 'Status added successfully.',
                'status' => $status
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add status: ' . $e->getMessage()]);
        }
    }

    /**
     * Update status.
     */
    public function updateStatus(UpdateStatusRequest $request, Workspace $workspace, Space $space, Status $status): RedirectResponse
    {
        try {
            $this->spaceService->updateStatus($status, $request->validated());

            return redirect()->back()->with('success', 'Status updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update status: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete status.
     */
    public function deleteStatus(Request $request, Workspace $workspace, Space $space, Status $status): RedirectResponse
    {
        $validated = $request->validate([
            'move_to_status_id' => 'nullable|exists:statuses,id',
        ]);

        try {
            $this->spaceService->deleteStatus($status, $validated['move_to_status_id'] ?? null);

            return redirect()->back()->with('success', 'Status deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete status: ' . $e->getMessage()]);
        }
    }

    /**
     * Reorder statuses.
     */
    public function reorderStatuses(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        try {
            $this->spaceService->reorderStatuses($space, $request->order);

            return redirect()->back()->with('success', 'Statuses reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder statuses: ' . $e->getMessage()]);
        }
    }
}
