<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreTaskListRequest;
use App\Http\Requests\UpdateTaskListRequest;
use App\Models\Folder;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TaskListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskListController extends Controller
{
    public function __construct(
        protected TaskListService $taskListService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a newly created list.
     */
    public function store(StoreTaskListRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        $folder = $request->folder_id ? Folder::find($request->folder_id) : null;

        if ($folder && (int) $folder->space_id !== (int) $space->id) {
            return back()->withErrors(['error' => 'Folder must belong to the same space.']);
        }

        $list = $this->taskListService->create(
            $request->validated(),
            $space,
            $request->user(),
            $folder
        );

        return redirect()
            ->route('lists.show', [$workspace, $space, $list])
            ->with('success', 'List created successfully.');
    }

    /**
     * Display the specified list (Board view).
     */
    public function show(Request $request, Workspace $workspace, Space $space, TaskList $list): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canViewProject($request->user(), $list), 403);
        $requestedTaskId = (int) $request->integer('task_id');
        $parentTask = null;

        if ($requestedTaskId > 0) {
            // Accept task_id only if the task belongs to the currently opened list.
            $parentTask = $list->tasks()->whereKey($requestedTaskId)->first();
        }

        $tasksByStatus = $this->taskListService->getWithTasksByStatus($list, $parentTask?->id);

        $workspace->load([
            'spaces' => fn($q) => $q->with([
                'folders.lists',
                'listsWithoutFolder',
            ])->orderBy('position'),
            'members',
            'labels',
        ]);

        // Filter statuses based on whether viewing subtasks or tasks
        $statusesQuery = $space->statuses()->orderBy('position');
        if ($parentTask) {
            $statusesQuery->forSubtasks(); // Only subtask-applicable statuses
        } else {
            $statusesQuery->forTasks(); // Only task-applicable statuses
        }

        return Inertia::render('Lists/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
            'tasksByStatus' => $tasksByStatus,
            'statuses' => $statusesQuery->get(),
            'sprints' => $list->sprints()->withCount('subtasks')->orderBy('position')->get(),
            'parentTask' => $parentTask,
            'projectMembers' => $list->members()->get(['users.id', 'users.name', 'users.email']),
        ]);
    }

    /**
     * Display product access settings.
     */
    public function settings(Request $request, Workspace $workspace, Space $space, TaskList $list): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canViewProject($request->user(), $list), 403);

        $workspace->load('members');
        $list->load('members');

        $listMemberIds = $list->members->pluck('id');

        $mapUser = fn($member, ?string $role = null) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'initials' => $member->initials,
            'avatar_color' => $member->avatar_color,
            'profile_photo_url' => $member->profile_photo_url,
            'role' => $role,
        ];

        return Inertia::render('Lists/Settings', [
            'workspace' => $workspace,
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
            ],
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'is_archived' => (bool) $list->is_archived,
            ],
            'members' => $list->members
                ->map(fn($member) => $mapUser($member, $member->pivot?->role))
                ->values(),
            'availableUsers' => $workspace->members
                ->filter(fn($member) => !$listMemberIds->contains($member->id))
                ->map(fn($member) => $mapUser($member))
                ->values(),
            'canManageMembers' => $this->accessService->canManageProjectMembers($request->user(), $list),
        ]);
    }

    /**
     * Update the specified list.
     */
    public function update(UpdateTaskListRequest $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $this->taskListService->update($list, $request->validated(), $request->user());

        return back()->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified list.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canDeleteProject($request->user(), $list), 403);
        $this->taskListService->delete($list, $request->user());

        return redirect()
            ->route('spaces.show', [$workspace, $space])
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Archive the specified list.
     */
    public function archive(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $this->taskListService->archive($list, $request->user());

        return back()->with('success', 'List archived successfully.');
    }

    /**
     * Unarchive the specified list.
     */
    public function unarchive(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $this->taskListService->unarchive($list, $request->user());

        return back()->with('success', 'List restored successfully.');
    }

    /**
     * Move list to folder.
     */
    public function moveToFolder(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $request->validate([
            'folder_id' => [
                'nullable',
                Rule::exists('folders', 'id')->where(fn($query) => $query->where('space_id', $space->id)),
            ],
        ]);

        $folder = $request->folder_id ? Folder::where('space_id', $space->id)->find($request->folder_id) : null;

        $this->taskListService->moveToFolder($list, $folder, $request->user());

        return back()->with('success', 'List moved successfully.');
    }

    /**
     * Reorder lists.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        $this->taskListService->reorder($space, $request->order);

        return back();
    }

    /**
     * Duplicate the list.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $newList = $this->taskListService->duplicate($list, $request->user());

        return redirect()
            ->route('lists.show', [$workspace, $space, $newList])
            ->with('success', 'List duplicated successfully.');
    }

    /**
     * Change product status (for kanban board drag-and-drop).
     */
    public function changeStatus(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $list), 403);
        $request->validate([
            'status_id' => [
                'required',
                Rule::exists('statuses', 'id')->where(fn($query) => $query->where('space_id', $space->id)),
            ],
        ]);

        $list->update(['status_id' => $request->status_id]);

        return back()->with('success', 'Product status updated.');
    }

    public function addMember(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $list), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:project_owner,project_manager,development_team,guest'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->taskListService->addMember($list, $user, $validated['role'], $request->user());

        return back()->with('success', 'Project member added successfully.');
    }

    public function updateMemberRole(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $list), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:project_owner,project_manager,development_team,guest'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->taskListService->updateMemberRole($list, $user, $validated['role'], $request->user());

        return back()->with('success', 'Project member role updated successfully.');
    }

    public function removeMember(Request $request, Workspace $workspace, Space $space, TaskList $list): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $list->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $list), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $this->taskListService->removeMember($list, $user, $request->user());

        return back()->with('success', 'Project member removed successfully.');
    }
}
