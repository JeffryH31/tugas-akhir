<?php

use App\Models\Activity;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new WorkspaceService();
    $this->owner = $this->createUser();
});

test('create workspace', function () {
    $workspace = $this->service->create([
        'name' => 'My Workspace',
    ], $this->owner);

    expect($workspace)->toBeInstanceOf(Workspace::class);
    expect($workspace->name)->toBe('My Workspace');
    expect($workspace->owner_id)->toBe($this->owner->id);
});

test('create workspace logs created activity', function () {
    $this->service->create(['name' => 'Logged WS'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Workspace::class)
        ->first();

    expect($activity)->not->toBeNull();
});

test('create workspace with custom color', function () {
    $workspace = $this->service->create([
        'name' => 'Colored WS',
        'color' => '#FF6600',
    ], $this->owner);

    expect($workspace->color)->toBe('#FF6600');
});

test('update workspace changes name and logs activity', function () {
    $h = $this->createFullHierarchy($this->owner);

    $updated = $this->service->update($h['workspace'], [
        'name' => 'Updated Workspace',
    ], $this->owner);

    expect($updated->name)->toBe('Updated Workspace');

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('update workspace without name change does not log activity', function () {
    $h = $this->createFullHierarchy($this->owner);

    $this->service->update($h['workspace'], [
        'name' => $h['workspace']->name,
        'description' => 'New desc',
    ], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->toBeNull();
});

test('delete workspace removes it and logs activity', function () {
    $workspace = $this->service->create(['name' => 'Delete Me'], $this->owner);

    $this->service->delete($workspace, $this->owner);

    expect(Workspace::find($workspace->id))->toBeNull();
});

test('addMember adds user with role', function () {
    $h = $this->createFullHierarchy($this->owner);
    $member = $this->createUser();

    $this->service->addMember($h['workspace'], $member, 'member', $this->owner);

    $wsMember = $h['workspace']->members()->where('user_id', $member->id)->first();
    expect($wsMember)->not->toBeNull();
    expect($wsMember->pivot->role)->toBe('member');
});

test('addMember logs member_added activity', function () {
    $h = $this->createFullHierarchy($this->owner);
    $member = $this->createUser();

    $this->service->addMember($h['workspace'], $member, 'admin', $this->owner);

    $activity = Activity::where('action', 'member_added')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['role'])->toBe('admin');
});

test('removeMember detaches user and logs activity', function () {
    $h = $this->createFullHierarchy($this->owner);
    $member = $this->createUser();
    $h['workspace']->addMember($member, 'member');

    $this->service->removeMember($h['workspace'], $member, $this->owner);

    expect($h['workspace']->members()->where('user_id', $member->id)->exists())->toBeFalse();

    $activity = Activity::where('action', 'member_removed')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('updateMemberRole changes role and logs activity', function () {
    $h = $this->createFullHierarchy($this->owner);
    $member = $this->createUser();
    $h['workspace']->addMember($member, 'member');

    $this->service->updateMemberRole($h['workspace'], $member, 'admin', $this->owner);

    $updated = $h['workspace']->members()->where('user_id', $member->id)->first();
    expect($updated->pivot->role)->toBe('admin');

    $activity = Activity::where('action', 'member_role_updated')
        ->where('subject_type', Workspace::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('getWorkspacesForUser returns workspaces with member counts', function () {
    $this->createFullHierarchy($this->owner);

    $workspaces = $this->service->getWorkspacesForUser($this->owner);

    expect($workspaces->count())->toBeGreaterThanOrEqual(1);
});

test('getStatistics returns workspace metrics', function () {
    $h = $this->createFullHierarchy($this->owner);

    $stats = $this->service->getStatistics($h['workspace']);

    expect($stats)->toHaveKeys([
        'spaces_count',
        'lists_count',
        'tasks_count',
        'completed_subtasks_count',
        'overdue_subtasks_count',
        'members_count',
    ]);
    expect($stats['spaces_count'])->toBe(1);
    expect($stats['members_count'])->toBeGreaterThanOrEqual(1);
});
