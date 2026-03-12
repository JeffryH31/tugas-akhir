<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected WorkspaceService $workspaceService
    ) {}

    /**
     * Display a listing of workspaces.
     */
    public function index(Request $request): Response
    {
        $workspaces = $this->workspaceService->getWorkspacesForUser($request->user());

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceService->create(
            $request->validated(),
            $request->user()
        );

        return back()->with('success', 'Workspace created successfully.');
    }

    /**
     * Display workspace settings.
     */
    public function settings(Request $request, Workspace $workspace): Response
    {
        $workspace->load(['members', 'labels']);

        $members = $workspace->members;

        $availableUsers = User::whereNotIn('id', $members->pluck('id'))
            ->get();

        return Inertia::render('Workspaces/Settings', [
            'workspace' => $workspace,
            'members' => $members,
            'availableUsers' => $availableUsers,
        ]);
    }

    /**
     * Display the specified workspace.
     */
    public function show(Request $request, Workspace $workspace): Response
    {
        $workspace->load([
            'spaces' => fn($q) => $q->withCount(['lists', 'tasks'])
                ->orderBy('position'),
        ]);

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update the specified workspace.
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        try {
            $this->authorize('update', $workspace);

            $this->workspaceService->update(
                $workspace,
                $request->validated(),
                $request->user()
            );

            return redirect()->back()->with('success', 'Workspace updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update workspace: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified workspace.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        try {
            $this->workspaceService->delete($workspace, $request->user());

            return redirect()
                ->route('dashboard')
                ->with('success', 'Workspace deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete workspace: ' . $e->getMessage()]);
        }
    }

    /**
     * Add member to workspace.
     */
    public function addMember(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:admin,member,guest',
        ]);

        try {
            $this->authorize('update', $workspace);

            $user = User::findOrFail($validated['user_id']);

            $this->workspaceService->addMember(
                $workspace,
                $user,
                $validated['role'] ?? 'member',
                $request->user()
            );

            return redirect()->back()->with('success', 'Member added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add member: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove member from workspace.
     */
    public function removeMember(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $this->authorize('update', $workspace);

            $user = User::findOrFail($validated['user_id']);

            $this->workspaceService->removeMember($workspace, $user, $request->user());

            return redirect()->back()->with('success', 'Member removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove member: ' . $e->getMessage()]);
        }
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member,guest',
        ]);

        try {
            $this->authorize('update', $workspace);

            $user = User::findOrFail($validated['user_id']);

            $this->workspaceService->updateMemberRole(
                $workspace,
                $user,
                $validated['role'],
                $request->user()
            );

            return redirect()->back()->with('success', 'Member role updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update member role: ' . $e->getMessage()]);
        }
    }
}
