<?php

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\User;
use App\Services\SubtaskService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SubtaskService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create subtask with correct fields', function () {
    $subtask = $this->service->create([
        'name' => 'Implement login',
        'description' => 'Build login page',
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask)->toBeInstanceOf(Subtask::class);
    expect($subtask->name)->toBe('Implement login');
    expect($subtask->description)->toBe('Build login page');
    expect($subtask->task_id)->toBe($this->hierarchy['task']->id);
    expect($subtask->created_by)->toBe($this->owner->id);
});

test('create subtask logs created activity', function () {
    $subtask = $this->service->create([
        'name' => 'New subtask',
    ], $this->hierarchy['task'], $this->owner);

    $activity = Activity::where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->where('action', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('New subtask');
    expect($activity->user_id)->toBe($this->owner->id);
});

test('create subtask auto-populates baseline dates from start/due', function () {
    $subtask = $this->service->create([
        'name' => 'Dated subtask',
        'start_date' => '2026-05-01',
        'due_date' => '2026-05-15',
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->baseline_start_date->toDateString())->toBe('2026-05-01');
    expect($subtask->baseline_due_date->toDateString())->toBe('2026-05-15');
});

test('create subtask syncs assignees and labels', function () {
    $assignee = $this->createUser();
    $label = $this->createLabel($this->hierarchy['workspace']);

    $subtask = $this->service->create([
        'name' => 'Team subtask',
        'assignee_ids' => [$assignee->id],
        'label_ids' => [$label->id],
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->assignees)->toHaveCount(1);
    expect($subtask->assignees->first()->id)->toBe($assignee->id);
    expect($subtask->labels)->toHaveCount(1);
    expect($subtask->labels->first()->id)->toBe($label->id);
});

test('update tracks field changes in activity log', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Original',
    ]);

    $this->service->update($subtask, [
        'name' => 'Updated Name',
    ], $this->owner);

    $activity = Activity::where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->where('action', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['name']['old'])->toBe('Original');
    expect($activity->changes['name']['new'])->toBe('Updated Name');
});

test('update assignees logs assigned and unassigned activities', function () {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $subtask = $this->createSubtask($this->hierarchy['task']);
    $subtask->assignees()->attach($user1->id, [
        'assigned_by' => $this->owner->id,
    ]);

    $this->service->update($subtask, [
        'assignee_ids' => [$user2->id],
    ], $this->owner);

    $assigned = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'assigned')
        ->first();

    $unassigned = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'unassigned')
        ->first();

    expect($assigned)->not->toBeNull();
    expect($assigned->properties['assignee_name'])->toBe($user2->name);
    expect($unassigned)->not->toBeNull();
    expect($unassigned->properties['assignee_name'])->toBe($user1->name);
});

test('update status change logs status_changed with status names', function () {
    $statuses = $this->hierarchy['statuses'];
    $openStatus = $statuses->firstWhere('type', 'open');
    $closedStatus = $statuses->firstWhere('type', 'closed');

    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'status_id' => $openStatus->id,
    ]);

    $this->service->update($subtask, [
        'status_id' => $closedStatus->id,
    ], $this->owner);

    $activity = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'status_changed')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['status']['old'])->toBe('Open');
    expect($activity->changes['status']['new'])->toBe('Completed');
});

test('update priority change logs priority_changed with labels', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'priority_level' => PriorityLevel::Low->value,
    ]);

    $this->service->update($subtask, [
        'priority_level' => PriorityLevel::High->value,
    ], $this->owner);

    $activity = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'priority_changed')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['priority']['old'])->toBe('Low');
    expect($activity->changes['priority']['new'])->toBe('High');
});

test('update sprint_id rejects sprint from different product', function () {
    $otherList = \App\Models\TaskList::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Other List',
        'created_by' => $this->owner->id,
    ]);

    $foreignSprint = $this->createSprint($otherList, ['name' => 'Foreign Sprint']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    expect(fn () => $this->service->update($subtask, [
        'sprint_id' => $foreignSprint->id,
    ], $this->owner))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('delete soft-deletes subtask and logs deleted_subtask activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'To Delete',
    ]);

    $this->service->delete($subtask, $this->owner);

    expect(Subtask::find($subtask->id))->toBeNull();
    expect(Subtask::withTrashed()->find($subtask->id))->not->toBeNull();

    $activity = Activity::where('action', 'deleted_subtask')
        ->where('subject_type', \App\Models\Task::class)
        ->where('subject_id', $this->hierarchy['task']->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('To Delete');
});

test('update with no actual changes does not log activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Same Name',
    ]);

    $countBefore = Activity::count();

    $this->service->update($subtask, [
        'name' => 'Same Name',
    ], $this->owner);

    expect(Activity::count())->toBe($countBefore);
});

test('reorder updates subtask positions', function () {
    $a = $this->createSubtask($this->hierarchy['task'], ['name' => 'First']);
    $b = $this->createSubtask($this->hierarchy['task'], ['name' => 'Second']);
    $c = $this->createSubtask($this->hierarchy['task'], ['name' => 'Third']);

    // Reorder: C, A, B
    $this->service->reorder($this->hierarchy['task'], [$c->id, $a->id, $b->id]);

    expect($c->fresh()->position)->toBe(0);
    expect($a->fresh()->position)->toBe(1);
    expect($b->fresh()->position)->toBe(2);
});
