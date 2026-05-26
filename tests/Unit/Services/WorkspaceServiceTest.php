<?php

use App\Models\Activity;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new WorkspaceService();
    $this->owner = $this->createUser();
});

// create

test('create workspace with name', function () {
    $workspace = $this->service->create(['name' => 'My Workspace'], $this->owner);

    expect($workspace)->toBeInstanceOf(Workspace::class);
    expect($workspace->name)->toBe('My Workspace');
    expect($workspace->slug)->toBe('my-workspace');
    expect($workspace->color)->toBe('#7C3AED');
});

test('create workspace adds owner as member', function () {
    $workspace = $this->service->create(['name' => 'Test WS'], $this->owner);

    $member = $workspace->members()->where('user_id', $this->owner->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('owner');
});

test('create workspace uses custom color', function () {
    $workspace = $this->service->create(['name' => 'Colored', 'color' => '#FF0000'], $this->owner);

    expect($workspace->color)->toBe('#FF0000');
});

test('create workspace logs activity', function () {
    $this->service->create(['name' => 'Logged WS'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
});

// update

test('update workspace name', function () {
    $workspace = $this->service->create(['name' => 'Old Name'], $this->owner);

    $updated = $this->service->update($workspace, ['name' => 'New Name'], $this->owner);

    expect($updated->name)->toBe('New Name');
});

test('update workspace logs activity when name changes', function () {
    $workspace = $this->service->create(['name' => 'Before'], $this->owner);

    $this->service->update($workspace, ['name' => 'After'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('update workspace does not log when name unchanged', function () {
    $workspace = $this->service->create(['name' => 'Same'], $this->owner);
    Activity::truncate(); // clear previous activities

    $this->service->update($workspace, ['name' => 'Same'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->toBeNull();
});

// delete

test('delete workspace removes it', function () {
    $workspace = $this->service->create(['name' => 'To Delete'], $this->owner);

    $this->service->delete($workspace, $this->owner);

    expect(Workspace::find($workspace->id))->toBeNull();
});

// addMember / removeMember / updateMemberRole

test('addMember adds user to workspace', function () {
    $workspace = $this->service->create(['name' => 'WS'], $this->owner);
    $user = $this->createUser();

    $this->service->addMember($workspace, $user, 'member', $this->owner);

    expect($workspace->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeMember removes user from workspace', function () {
    $workspace = $this->service->create(['name' => 'WS'], $this->owner);
    $user = $this->createUser();
    $workspace->addMember($user, 'member');

    $this->service->removeMember($workspace, $user, $this->owner);

    expect($workspace->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('updateMemberRole changes role', function () {
    $workspace = $this->service->create(['name' => 'WS'], $this->owner);
    $user = $this->createUser();
    $workspace->addMember($user, 'member');

    $this->service->updateMemberRole($workspace, $user, 'admin', $this->owner);

    $role = $workspace->members()->where('user_id', $user->id)->first()->pivot->role;
    expect($role)->toBe('admin');
});

// getWorkspacesForUser

test('getWorkspacesForUser returns user workspaces', function () {
    $this->service->create(['name' => 'WS1'], $this->owner);
    $this->service->create(['name' => 'WS2'], $this->owner);

    $workspaces = $this->service->getWorkspacesForUser($this->owner);

    expect($workspaces->count())->toBeGreaterThanOrEqual(2);
});
