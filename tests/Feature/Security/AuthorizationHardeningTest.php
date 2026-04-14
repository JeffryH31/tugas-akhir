<?php

use App\Models\Folder;
use App\Models\Label;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use function Pest\Laravel\actingAs;

function createWorkspaceHierarchy(User $owner, string $suffix = 'A'): array
{
    $workspace = Workspace::create([
        'name' => "Workspace {$suffix}",
        'color' => '#1D4ED8',
        'owner_id' => $owner->id,
    ]);

    $space = Space::create([
        'workspace_id' => $workspace->id,
        'name' => "Space {$suffix}",
        'description' => "Space {$suffix} description",
        'is_private' => false,
        'created_by' => $owner->id,
    ]);

    $list = TaskList::create([
        'space_id' => $space->id,
        'name' => "List {$suffix}",
        'description' => "List {$suffix} description",
        'created_by' => $owner->id,
    ]);

    $task = Task::create([
        'task_list_id' => $list->id,
        'name' => "Task {$suffix}",
        'description' => "Task {$suffix} description",
        'created_by' => $owner->id,
    ]);

    return [
        'workspace' => $workspace,
        'space' => $space,
        'list' => $list,
        'task' => $task,
    ];
}

test('non-member cannot switch active workspace', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $foreignWorkspace = Workspace::create([
        'name' => 'Foreign Workspace',
        'color' => '#7C3AED',
        'owner_id' => $otherOwner->id,
    ]);

    actingAs($owner)
        ->post(route('workspaces.switch', $foreignWorkspace->id))
        ->assertForbidden();
});

test('workspace admin cannot delete workspace unless owner', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Admin Delete Guard',
        'color' => '#0EA5E9',
        'owner_id' => $owner->id,
    ]);

    $workspace->members()->syncWithoutDetaching([
        $admin->id => ['role' => 'admin'],
    ]);

    actingAs($admin)
        ->delete(route('workspaces.destroy', $workspace->id))
        ->assertForbidden();
});

test('recycle bin requires workspace membership', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Recycle Bin Guard',
        'color' => '#0891B2',
        'owner_id' => $owner->id,
    ]);

    actingAs($stranger)
        ->get(route('workspaces.recycle-bin.index', $workspace->id))
        ->assertForbidden();
});

test('cannot attach label from different workspace to task', function () {
    $owner = User::factory()->create();

    $primary = createWorkspaceHierarchy($owner, 'Primary');
    $secondaryWorkspace = Workspace::create([
        'name' => 'Secondary Workspace',
        'color' => '#DC2626',
        'owner_id' => $owner->id,
    ]);

    $foreignLabel = Label::create([
        'workspace_id' => $secondaryWorkspace->id,
        'name' => 'Foreign Label',
        'color' => '#EF4444',
    ]);

    actingAs($owner)
        ->post(route('tasks.labels.add', [
            $primary['workspace']->id,
            $primary['space']->id,
            $primary['list']->id,
            $primary['task']->id,
        ]), [
            'label_id' => $foreignLabel->id,
        ])
        ->assertSessionHasErrors(['label_id']);
});

test('scoped bindings reject task outside list context', function () {
    $owner = User::factory()->create();
    $hierarchy = createWorkspaceHierarchy($owner, 'Scope');

    $otherList = TaskList::create([
        'space_id' => $hierarchy['space']->id,
        'name' => 'Other List',
        'description' => 'Other List description',
        'created_by' => $owner->id,
    ]);

    $otherTask = Task::create([
        'task_list_id' => $otherList->id,
        'name' => 'Task In Other List',
        'description' => 'Task In Other List description',
        'created_by' => $owner->id,
    ]);

    actingAs($owner)
        ->patch(route('tasks.update', [
            $hierarchy['workspace']->id,
            $hierarchy['space']->id,
            $hierarchy['list']->id,
            $otherTask->id,
        ]), ['name' => 'Hacked'])
        ->assertNotFound();
});

test('cannot create folder using parent from another space', function () {
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Folder Scope Guard',
        'description' => 'Folder Scope Guard description',
        'color' => '#16A34A',
        'owner_id' => $owner->id,
    ]);

    $spaceA = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Space A',
        'description' => 'Space A description',
        'is_private' => false,
        'created_by' => $owner->id,
    ]);

    $spaceB = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Space B',
        'description' => 'Space B description',
        'is_private' => false,
        'created_by' => $owner->id,
    ]);

    $foreignParent = Folder::create([
        'space_id' => $spaceB->id,
        'name' => 'Foreign Parent Folder',
        'created_by' => $owner->id,
    ]);

    actingAs($owner)
        ->post(route('folders.store', [$workspace->id, $spaceA->id]), [
            'name' => 'New Folder',
            'parent_id' => $foreignParent->id,
        ])
        ->assertSessionHasErrors(['error']);
});

test('workspace member without manage permission cannot create folder', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Folder Permission Guard',
        'description' => 'Folder Permission Guard description',
        'color' => '#9333EA',
        'owner_id' => $owner->id,
    ]);

    $workspace->members()->syncWithoutDetaching([
        $member->id => ['role' => 'member'],
    ]);

    $space = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Public Space',
        'description' => 'Public Space description',
        'is_private' => false,
        'created_by' => $owner->id,
    ]);

    actingAs($member)
        ->post(route('folders.store', [$workspace->id, $space->id]), [
            'name' => 'Unauthorized Folder',
        ])
        ->assertForbidden();
});
