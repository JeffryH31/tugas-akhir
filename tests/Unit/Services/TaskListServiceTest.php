<?php

use App\Models\Activity;
use App\Models\Folder;
use App\Models\TaskList;
use App\Services\TaskListService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TaskListService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// ── create ────────────────────────────────────────────────────────────────────

test('create list in space', function () {
    $list = $this->service->create(
        ['name' => 'Product Backlog'],
        $this->hierarchy['space'],
        $this->owner
    );

    expect($list)->toBeInstanceOf(TaskList::class);
    expect($list->name)->toBe('Product Backlog');
    expect($list->space_id)->toBe($this->hierarchy['space']->id);
});

test('create list adds creator as project_owner', function () {
    $list = $this->service->create(
        ['name' => 'My List'],
        $this->hierarchy['space'],
        $this->owner
    );

    $member = $list->members()->where('user_id', $this->owner->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('project_owner');
});

test('create list in folder', function () {
    $folder = Folder::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Test Folder',
        'slug' => 'test-folder',
        'created_by' => $this->owner->id,
    ]);

    $list = $this->service->create(
        ['name' => 'In Folder'],
        $this->hierarchy['space'],
        $this->owner,
        $folder
    );

    expect($list->folder_id)->toBe($folder->id);
});

test('create list logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', TaskList::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

// ── update ────────────────────────────────────────────────────────────────────

test('update list name', function () {
    $list = $this->hierarchy['list'];

    $updated = $this->service->update($list, ['name' => 'Renamed List'], $this->owner);

    expect($updated->name)->toBe('Renamed List');
});

test('update list logs activity when changed', function () {
    $list = $this->hierarchy['list'];
    Activity::truncate();

    $this->service->update($list, ['name' => 'Different Name'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', TaskList::class)
        ->first();
    expect($activity)->not->toBeNull();
});

// ── delete ────────────────────────────────────────────────────────────────────

test('delete list soft deletes it', function () {
    $list = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($list, $this->owner);

    expect(TaskList::find($list->id))->toBeNull();
    expect(TaskList::withTrashed()->find($list->id))->not->toBeNull();
});

// ── membership ────────────────────────────────────────────────────────────────

test('addMember adds user to list', function () {
    $user = $this->createUser();

    $this->service->addMember($this->hierarchy['list'], $user, 'development_team', $this->owner);

    expect($this->hierarchy['list']->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeMember removes user from list', function () {
    $user = $this->createUser();
    $this->hierarchy['list']->addMember($user, 'development_team');

    $this->service->removeMember($this->hierarchy['list'], $user, $this->owner);

    expect($this->hierarchy['list']->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('updateMemberRole changes role', function () {
    $user = $this->createUser();
    $this->hierarchy['list']->addMember($user, 'development_team');

    $this->service->updateMemberRole($this->hierarchy['list'], $user, 'project_manager', $this->owner);

    $role = $this->hierarchy['list']->members()->where('user_id', $user->id)->first()->pivot->role;
    expect($role)->toBe('project_manager');
});

// ── reorder ───────────────────────────────────────────────────────────────────

test('reorder updates list positions', function () {
    $l1 = $this->service->create(['name' => 'L1'], $this->hierarchy['space'], $this->owner);
    $l2 = $this->service->create(['name' => 'L2'], $this->hierarchy['space'], $this->owner);

    $this->service->reorder($this->hierarchy['space'], [$l2->id, $l1->id]);

    expect($l2->fresh()->position)->toBe(0);
    expect($l1->fresh()->position)->toBe(1);
});

// ── moveToFolder ──────────────────────────────────────────────────────────────

test('moveToFolder updates folder_id', function () {
    $list = $this->service->create(['name' => 'Movable'], $this->hierarchy['space'], $this->owner);
    $folder = Folder::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Destination',
        'slug' => 'destination',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->moveToFolder($list, $folder, $this->owner);

    expect($moved->folder_id)->toBe($folder->id);
});
