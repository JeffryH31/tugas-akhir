<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Models\Space;
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

        // Load workspace with all spaces for sidebar
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
    public function addStatus(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_closed' => 'nullable|boolean',
        ]);

        try {
            $status = $this->spaceService->addStatus($space, $validated);

            return redirect()->back()->with([
                'success' => 'Status added successfully.',
                'status' => $status
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add status: ' . $e->getMessage()]);
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
