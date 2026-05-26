<?php

use App\Models\Activity;
use App\Models\Space;
use App\Models\Status;
use App\Services\SpaceService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SpaceService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// create

test('create space with name', function () {
    $space = $this->service->create(
        ['name' => 'Backend'],
        $this->hierarchy['workspace'],
        $this->owner
    );

    expect($space)->toBeInstanceOf(Space::class);
    expect($space->name)->toBe('Backend');
    expect($space->workspace_id)->toBe($this->hierarchy['workspace']->id);
});

test('create space with custom color', function () {
    $space = $this->service->create(
        ['name' => 'Colored', 'color' => '#FF5500'],
        $this->hierarchy['workspace'],
        $this->owner
    );

    expect($space->color)->toBe('#FF5500');
});

test('create space logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['workspace'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Space::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

// update

test('update space name', function () {
    $space = $this->hierarchy['space'];

    $updated = $this->service->update($space, ['name' => 'Renamed'], $this->owner);

    expect($updated->name)->toBe('Renamed');
});

// delete

test('delete space removes it', function () {
    $space = $this->service->create(['name' => 'To Delete'], $this->hierarchy['workspace'], $this->owner);

    $this->service->delete($space, $this->owner);

    expect(Space::find($space->id))->toBeNull();
});

// membership

test('addMember adds user to space with role', function () {
    $user = $this->createUser();

    $this->service->addMember($this->hierarchy['space'], $user, 'member', $this->owner);

    expect($this->hierarchy['space']->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeMember removes user from space', function () {
    $user = $this->createUser();
    $this->hierarchy['space']->members()->attach($user->id, ['role' => 'member']);

    $this->service->removeMember($this->hierarchy['space'], $user, $this->owner);

    expect($this->hierarchy['space']->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('updateMemberRole changes space member role', function () {
    $user = $this->createUser();
    $this->hierarchy['space']->members()->attach($user->id, ['role' => 'member']);

    $this->service->updateMemberRole($this->hierarchy['space'], $user, 'admin', $this->owner);

    $role = $this->hierarchy['space']->members()->where('user_id', $user->id)->first()->pivot->role;
    expect($role)->toBe('admin');
});

// addStatus

test('addStatus creates custom status', function () {
    $status = $this->service->addStatus($this->hierarchy['space'], [
        'name' => 'Testing',
    ]);

    expect($status)->toBeInstanceOf(Status::class);
    expect($status->name)->toBe('Testing');
    expect($status->type)->toBe('custom');
});

// reorder

test('reorder updates space positions', function () {
    $s1 = $this->service->create(['name' => 'First'], $this->hierarchy['workspace'], $this->owner);
    $s2 = $this->service->create(['name' => 'Second'], $this->hierarchy['workspace'], $this->owner);

    $this->service->reorder($this->hierarchy['workspace'], [$s2->id, $s1->id]);

    expect($s2->fresh()->position)->toBe(0);
    expect($s1->fresh()->position)->toBe(1);
});
