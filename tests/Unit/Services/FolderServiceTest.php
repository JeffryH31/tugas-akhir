<?php

use App\Models\Activity;
use App\Models\Folder;
use App\Services\FolderService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new FolderService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// ── create ────────────────────────────────────────────────────────────────────

test('create folder with name', function () {
    $folder = $this->service->create(
        ['name' => 'New Folder'],
        $this->hierarchy['space'],
        $this->owner
    );

    expect($folder)->toBeInstanceOf(Folder::class);
    expect($folder->name)->toBe('New Folder');
    expect($folder->space_id)->toBe($this->hierarchy['space']->id);
});

test('create folder with parent', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->hierarchy['space'], $this->owner);

    $child = $this->service->create(['name' => 'Child'], $this->hierarchy['space'], $this->owner, $parent);

    expect($child->parent_id)->toBe($parent->id);
});

test('create folder logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Folder::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

// ── update ────────────────────────────────────────────────────────────────────

test('update folder name', function () {
    $folder = $this->service->create(['name' => 'Old'], $this->hierarchy['space'], $this->owner);

    $updated = $this->service->update($folder, ['name' => 'New'], $this->owner);

    expect($updated->name)->toBe('New');
});

test('update folder logs activity when name changes', function () {
    $folder = $this->service->create(['name' => 'Before'], $this->hierarchy['space'], $this->owner);

    $this->service->update($folder, ['name' => 'After'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Folder::class)
        ->where('subject_id', $folder->id)
        ->first();
    expect($activity)->not->toBeNull();
});

// ── delete ────────────────────────────────────────────────────────────────────

test('delete folder removes it', function () {
    $folder = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($folder, $this->owner);

    expect(Folder::find($folder->id))->toBeNull();
});

// ── move ──────────────────────────────────────────────────────────────────────

test('move folder to new parent', function () {
    $folder = $this->service->create(['name' => 'Movable'], $this->hierarchy['space'], $this->owner);
    $target = $this->service->create(['name' => 'Target'], $this->hierarchy['space'], $this->owner);

    $moved = $this->service->move($folder, $target, $this->owner);

    expect($moved->parent_id)->toBe($target->id);
});

// ── reorder ───────────────────────────────────────────────────────────────────

test('reorder updates folder positions', function () {
    $f1 = $this->service->create(['name' => 'F1'], $this->hierarchy['space'], $this->owner);
    $f2 = $this->service->create(['name' => 'F2'], $this->hierarchy['space'], $this->owner);

    $this->service->reorder($this->hierarchy['space'], [$f2->id, $f1->id]);

    expect($f2->fresh()->position)->toBe(0);
    expect($f1->fresh()->position)->toBe(1);
});
