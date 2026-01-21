<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\AddWorkspaceMemberRequest;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WorkspaceController
 *
 * Handles workspace management using Inertia.js (monolith).
 */
class WorkspaceController extends Controller
{
    /**
     * @var WorkspaceService
     */
    private WorkspaceService $workspaceService;

    /**
     * Constructor.
     *
     * @param WorkspaceService $workspaceService
     */
    public function __construct(WorkspaceService $workspaceService)
    {
        $this->workspaceService = $workspaceService;
    }

    /**
     * Store a newly created workspace.
     *
     * @param StoreWorkspaceRequest $request
     * @return RedirectResponse
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceService->createWorkspace(
            $request->user(),
            $request->validated()
        );

        return redirect()->route('dashboard')
            ->with('success', 'Workspace created successfully.');
    }

    /**
     * Update the specified workspace.
     *
     * @param UpdateWorkspaceRequest $request
     * @param Workspace $workspace
     * @return RedirectResponse
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $this->workspaceService->updateWorkspace($workspace, $request->validated());

        return back()->with('success', 'Workspace updated successfully.');
    }

    /**
     * Remove the specified workspace.
     *
     * @param Workspace $workspace
     * @return RedirectResponse
     */
    public function destroy(Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $this->workspaceService->deleteWorkspace($workspace);

        return redirect()->route('dashboard')
            ->with('success', 'Workspace deleted successfully.');
    }

    /**
     * Add a member to the workspace.
     *
     * @param AddWorkspaceMemberRequest $request
     * @param Workspace $workspace
     * @return RedirectResponse
     */
    public function addMember(AddWorkspaceMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $user = User::where('email', $request->email)->firstOrFail();
        $role = $request->input('role', 'member');

        $this->workspaceService->addMember($workspace, $user, $role);

        return back()->with('success', 'Member added successfully.');
    }

    /**
     * Remove a member from the workspace.
     *
     * @param Workspace $workspace
     * @param User $user
     * @return RedirectResponse
     */
    public function removeMember(Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $this->workspaceService->removeMember($workspace, $user);

        return back()->with('success', 'Member deleted successfully.');
    }

    /**
     * Update member role in the workspace.
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param User $user
     * @return RedirectResponse
     */
    public function updateMemberRole(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        $this->workspaceService->updateMemberRole($workspace, $user, $request->role);

        return back()->with('success', 'Role member updated successfully.');
    }
}
