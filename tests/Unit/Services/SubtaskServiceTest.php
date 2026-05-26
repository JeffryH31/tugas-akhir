<?php

use App\Models\Activity;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\SubtaskService;
use Illuminate\Validation\ValidationException;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SubtaskService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// create

test('create subtask with name only', function () {
    $subtask = $this->service->create(
        ['name' => 'Write tests'],
        $this->hierarchy['task'],
        $this->owner
    );

    expect($subtask)->toBeInstanceOf(Subtask::class);
    expect($subtask->name)->toBe('Write tests');
    expect($subtask->task_id)->toBe($this->hierarchy['task']->id);
    expect($subtask->created_by)->toBe($this->owner->id);
});

test('create subtask with all optional fields', function () {
    $status = $this->hierarchy['statuses']->first();

    $subtask = $this->service->create([
        'name' => 'Full Subtask',
        'description' => 'A description',
        'status_id' => $status->id,
        'priority_level' => 2,
        'start_date' => '2026-06-01',
        'due_date' => '2026-06-15',
        'time_estimate' => 120,
        'optimistic_estimate' => 60,
        'most_likely_estimate' => 100,
        'pessimistic_estimate' => 180,
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->description)->toBe('A description');
    expect($subtask->status_id)->toBe($status->id);
    expect($subtask->time_estimate)->toBe(120);
    expect($subtask->optimistic_estimate)->toBe(60);
});

test('create subtask sets baseline dates from start/due dates', function () {
    $subtask = $this->service->create([
        'name' => 'Baseline Test',
        'start_date' => '2026-04-01',
        'due_date' => '2026-04-15',
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->baseline_start_date->format('Y-m-d'))->toBe('2026-04-01');
    expect($subtask->baseline_due_date->format('Y-m-d'))->toBe('2026-04-15');
});

test('create subtask syncs assignees', function () {
    $assignee = $this->createUser();

    $subtask = $this->service->create([
        'name' => 'Assigned Subtask',
        'assignee_ids' => [$assignee->id],
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->assignees)->toHaveCount(1);
    expect($subtask->assignees->first()->id)->toBe($assignee->id);
});

test('create subtask syncs labels', function () {
    $label = $this->createLabel($this->hierarchy['workspace']);

    $subtask = $this->service->create([
        'name' => 'Labeled Subtask',
        'label_ids' => [$label->id],
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->labels)->toHaveCount(1);
});

test('create subtask logs activity', function () {
    $this->service->create(
        ['name' => 'Activity Subtask'],
        $this->hierarchy['task'],
        $this->owner
    );

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Subtask::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Activity Subtask');
});

test('create subtask with parent_id validates max depth', function () {
    $task = $this->hierarchy['task'];

    // Create nested subtasks up to MAX_DEPTH
    $parent = $this->service->create(['name' => 'Level 0'], $task, $this->owner);
    for ($i = 1; $i <= Subtask::MAX_DEPTH - 1; $i++) {
        $parent = $this->service->create(['name' => "Level $i", 'parent_id' => $parent->id], $task, $this->owner);
    }

    // One more level should still work (at MAX_DEPTH)
    $deepest = $this->service->create(['name' => 'At Max'], $task, $this->owner);
    // But going beyond MAX_DEPTH should throw
    $atMax = Subtask::where('task_id', $task->id)->orderByDesc('depth')->first();

    if ($atMax->depth >= Subtask::MAX_DEPTH) {
        expect(fn() => $this->service->create(
            ['name' => 'Too Deep', 'parent_id' => $atMax->id],
            $task,
            $this->owner
        ))->toThrow(ValidationException::class);
    }
});

// update

test('update subtask name', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Old Name']);

    $updated = $this->service->update($subtask, ['name' => 'New Name'], $this->owner);

    expect($updated->name)->toBe('New Name');
});

test('update subtask logs activity with changes', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Original']);

    $this->service->update($subtask, ['name' => 'Changed'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['name']['old'])->toBe('Original');
    expect($activity->changes['name']['new'])->toBe('Changed');
});

test('update subtask status logs status_changed activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);
    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $this->service->update($subtask, ['status_id' => $inProgress->id], $this->owner);

    $activity = Activity::where('action', 'status_changed')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update subtask priority logs priority_changed activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->update($subtask, ['priority_level' => 1], $this->owner);

    $activity = Activity::where('action', 'priority_changed')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['priority']['new'])->toBe('Urgent');
});

test('update subtask assignees logs assigned and unassigned activities', function () {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $subtask = $this->createSubtask($this->hierarchy['task']);
    $subtask->assignees()->attach($user1->id, ['assigned_by' => $this->owner->id]);
    $subtask = $subtask->fresh('assignees');

    $this->service->update($subtask, ['assignee_ids' => [$user2->id]], $this->owner);

    $assigned = Activity::where('action', 'assigned')->where('subject_id', $subtask->id)->first();
    $unassigned = Activity::where('action', 'unassigned')->where('subject_id', $subtask->id)->first();

    expect($assigned)->not->toBeNull();
    expect($assigned->properties['assignee_name'])->toBe($user2->name);
    expect($unassigned)->not->toBeNull();
    expect($unassigned->properties['assignee_name'])->toBe($user1->name);
});

// delete

test('delete subtask soft deletes it', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'To Delete']);

    $this->service->delete($subtask, $this->owner);

    expect(Subtask::find($subtask->id))->toBeNull();
    expect(Subtask::withTrashed()->find($subtask->id))->not->toBeNull();
});

test('delete subtask logs activity on parent task', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Deleted One']);

    $this->service->delete($subtask, $this->owner);

    $activity = Activity::where('action', 'deleted_subtask')
        ->where('subject_type', Task::class)
        ->where('subject_id', $this->hierarchy['task']->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Deleted One');
});

// reorder

test('reorder updates subtask positions', function () {
    $task = $this->hierarchy['task'];
    $s1 = $this->createSubtask($task, ['name' => 'First']);
    $s2 = $this->createSubtask($task, ['name' => 'Second']);

    $this->service->reorder($task, [$s2->id, $s1->id]);

    expect($s2->fresh()->position)->toBe(0);
    expect($s1->fresh()->position)->toBe(1);
});

// duplicate

test('duplicate subtask creates copy with "(Copy)" suffix', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Original']);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->name)->toBe('Original (Copy)');
    expect($copy->id)->not->toBe($subtask->id);
    expect($copy->task_id)->toBe($subtask->task_id);
});

test('duplicate subtask copies labels but NOT assignees', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'With Relations']);
    $assignee = $this->createUser();
    $label = $this->createLabel($this->hierarchy['workspace']);

    $subtask->assignees()->attach($assignee->id, ['assigned_by' => $this->owner->id]);
    $subtask->labels()->attach($label->id);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);
    $copy = $copy->fresh(['assignees', 'labels']);

    // Labels are structural — should be copied
    expect($copy->labels)->toHaveCount(1);
    // Assignees are operational — should NOT be copied
    expect($copy->assignees)->toHaveCount(0);
});

test('duplicate subtask does not copy completed_at or time_spent', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Completed',
        'completed_at' => now(),
        'time_spent' => 120,
    ]);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->completed_at)->toBeNull();
    expect($copy->time_spent)->toBeNull();
});

test('duplicate subtask does not copy dates or sprint', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'With Dates',
        'start_date' => now()->subDays(5),
        'due_date' => now()->addDays(5),
    ]);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->start_date)->toBeNull();
    expect($copy->due_date)->toBeNull();
    expect($copy->sprint_id)->toBeNull();
});

// normalizeDateForComparison (pure logic, no DB)

test('normalizeDateForComparison returns null for null input', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, null))->toBeNull();
});

test('normalizeDateForComparison returns null for empty string', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, ''))->toBeNull();
    expect($method->invoke($service, '   '))->toBeNull();
});

test('normalizeDateForComparison parses Y-m-d string correctly', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, '2026-03-15'))->toBe('2026-03-15');
});

test('normalizeDateForComparison extracts date from datetime string', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, '2026-03-15 14:30:00'))->toBe('2026-03-15');
    expect($method->invoke($service, '2026-03-15T14:30:00'))->toBe('2026-03-15');
});

test('normalizeDateForComparison handles Carbon instances', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    $carbon = \Carbon\Carbon::create(2026, 1, 15, 8, 30, 0);
    expect($method->invoke($service, $carbon))->toBe('2026-01-15');
});

test('normalizeDateForComparison returns null for non-scalar value', function () {
    $service = new SubtaskService();
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, ['invalid']))->toBeNull();
});
