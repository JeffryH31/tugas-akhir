<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SpaceController
 *
 * Handles Space management .
 */
class SpaceController extends Controller
{
    private SpaceService $spaceService;

    public function __construct(SpaceService $spaceService)
    {
        $this->spaceService = $spaceService;
    }

    /**
     * Store a newly created space.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => 'required|exists:workspaces,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'is_private' => 'boolean',
        ]);

        $workspace = Workspace::findOrFail($validated['workspace_id']);
        $this->authorize('update', $workspace);

        $this->spaceService->create($workspace, $validated, $request->user());

        return redirect()->back()
            ->with('success', 'Space created successfully.');
    }

    /**
     * Update the specified space.
     */
    public function update(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'is_private' => 'boolean',
        ]);

        $this->spaceService->update($space, $validated);

        return redirect()->back()
            ->with('success', 'Space updated successfully.');
    }

    /**
     * Remove the specified space.
     */
    public function destroy(Space $space): RedirectResponse
    {
        $this->authorize('delete', $space);

        $this->spaceService->delete($space);

        return redirect()->route('dashboard')
            ->with('success', 'Space deleted successfully.');
    }

    /**
     * Toggle space starred status.
     */
    public function toggleStar(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('view', $space);

        $user = $request->user();
        $isStarred = $space->starredBy()->where('user_id', $user->id)->exists();

        if ($isStarred) {
            $space->starredBy()->detach($user->id);
            $message = 'Space removed from favorites.';
        } else {
            $space->starredBy()->attach($user->id);
            $message = 'Space added to favorites.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Add member to space.
     */
    public function addMember(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('manageMembers', $space);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'sometimes|string|in:admin,member',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->spaceService->addMember($space, $user, $validated['role'] ?? 'member');

        return redirect()->back()
            ->with('success', 'Member added successfully.');
    }

    /**
     * Remove member from space.
     */
    public function removeMember(Space $space, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $space);

        $this->spaceService->removeMember($space, $user);

        return redirect()->back()
            ->with('success', 'Member deleted successfully.');
    }
}
