<?php

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Task;
use App\Models\TaskList;
use App\Services\TaskService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TaskService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// create

test('create task with name', function () {
    $task = $this->service->create(
        ['name' => 'New Feature'],
        $this->hierarchy['list'],
        $this->owner
    );

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->name)->toBe('New Feature');
    expect($task->task_list_id)->toBe($this->hierarchy['list']->id);
    expect($task->created_by)->toBe($this->owner->id);
});

test('create task with all optional fields', function () {
    $status = $this->hierarchy['statuses']->first();

    $task = $this->service->create([
        'name' => 'Full Task',
        'description' => 'A description',
        'status_id' => $status->id,
        'priority_level' => 2,
        'start_date' => '2026-06-01',
        'due_date' => '2026-06-15',
        'time_estimate' => 480,
    ], $this->hierarchy['list'], $this->owner);

    expect($task->description)->toBe('A description');
    expect($task->status_id)->toBe($status->id);
    expect($task->time_estimate)->toBe(480);
});

test('create task syncs assignees', function () {
    $assignee = $this->createUser();

    $task = $this->service->create([
        'name' => 'Assigned Task',
        'assignee_ids' => [$assignee->id],
    ], $this->hierarchy['list'], $this->owner);

    expect($task->assignees)->toHaveCount(1);
    expect($task->assignees->first()->id)->toBe($assignee->id);
});

test('create task syncs labels', function () {
    $label = $this->createLabel($this->hierarchy['workspace']);

    $task = $this->service->create([
        'name' => 'Labeled Task',
        'label_ids' => [$label->id],
    ], $this->hierarchy['list'], $this->owner);

    expect($task->labels)->toHaveCount(1);
});

test('create task logs created activity', function () {
    $this->service->create(['name' => 'Activity Test'], $this->hierarchy['list'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Task::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Activity Test');
});

// update

test('update task name and description', function () {
    $task = $this->service->create(['name' => 'Old'], $this->hierarchy['list'], $this->owner);

    $updated = $this->service->update($task, [
        'name' => 'New Name',
        'description' => 'New Desc',
    ], $this->owner);

    expect($updated->name)->toBe('New Name');
    expect($updated->description)->toBe('New Desc');
});

test('update task logs changes activity', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);

    $this->service->update($task, ['name' => 'Changed'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update task status logs status_changed', function () {
    $task = $this->service->create(['name' => 'Status Test'], $this->hierarchy['list'], $this->owner);
    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $this->service->update($task, ['status_id' => $inProgress->id], $this->owner);

    $activity = Activity::where('action', 'status_changed')
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update task priority logs priority_changed', function () {
    $task = $this->service->create(['name' => 'Priority Test'], $this->hierarchy['list'], $this->owner);

    $this->service->update($task, ['priority_level' => 1], $this->owner);

    $activity = Activity::where('action', 'priority_changed')
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

// delete

test('delete task soft deletes and logs activity', function () {
    $task = $this->service->create(['name' => 'To Delete'], $this->hierarchy['list'], $this->owner);

    $this->service->delete($task, $this->owner);

    expect(Task::find($task->id))->toBeNull();
    expect(Task::withTrashed()->find($task->id))->not->toBeNull();

    $activity = Activity::where('action', 'deleted')
        ->where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->first();
    expect($activity)->not->toBeNull();
});

// changeStatus

test('changeStatus updates status and logs activity', function () {
    $task = $this->service->create(['name' => 'Status'], $this->hierarchy['list'], $this->owner);
    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $updated = $this->service->changeStatus($task, $inProgress, $this->owner);

    expect($updated->status_id)->toBe($inProgress->id);
});

// changePriority

test('changePriority updates priority and logs activity', function () {
    $task = $this->service->create(['name' => 'Priority'], $this->hierarchy['list'], $this->owner);

    $updated = $this->service->changePriority($task, PriorityLevel::High->value, $this->owner);

    expect($updated->priority_level)->toBe(PriorityLevel::High);
});

// assign / unassign

test('assign and unassign user', function () {
    $task = $this->service->create(['name' => 'Assign Test'], $this->hierarchy['list'], $this->owner);
    $assignee = $this->createUser();

    $assigned = $this->service->assign($task, $assignee, $this->owner);
    expect($assigned->assignees->pluck('id'))->toContain($assignee->id);

    $unassigned = $this->service->unassign($assigned, $assignee, $this->owner);
    expect($unassigned->assignees->pluck('id'))->not->toContain($assignee->id);
});

// move

test('move task to different list', function () {
    $task = $this->service->create(['name' => 'Movable'], $this->hierarchy['list'], $this->owner);

    $newList = TaskList::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'New List',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->move($task, $newList, $this->owner);

    expect($moved->task_list_id)->toBe($newList->id);
});

// reorder

test('reorder tasks updates positions', function () {
    $t1 = $this->service->create(['name' => 'Task 1'], $this->hierarchy['list'], $this->owner);
    $t2 = $this->service->create(['name' => 'Task 2'], $this->hierarchy['list'], $this->owner);

    $this->service->reorder($this->hierarchy['list'], [$t2->id, $t1->id]);

    expect($t2->fresh()->position)->toBe(0);
    expect($t1->fresh()->position)->toBe(1);
});

// addLabel / removeLabel

test('addLabel and removeLabel work correctly', function () {
    $task = $this->service->create(['name' => 'Label Test'], $this->hierarchy['list'], $this->owner);
    $label = $this->createLabel($this->hierarchy['workspace']);

    $labeled = $this->service->addLabel($task, $label, $this->owner);
    expect($labeled->labels->pluck('id'))->toContain($label->id);

    $unlabeled = $this->service->removeLabel($labeled, $label, $this->owner);
    expect($unlabeled->labels->pluck('id'))->not->toContain($label->id);
});

// duplicate

test('duplicate task creates copy with "(Copy)" suffix', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);
    $task = $task->fresh(['assignees', 'labels', 'subtasks']);

    $copy = $this->service->duplicate($task, $this->owner);

    expect($copy->name)->toBe('Original (Copy)');
    expect($copy->task_list_id)->toBe($task->task_list_id);
});

// getMyTasks

test('getMyTasks returns tasks assigned to user', function () {
    $task = $this->service->create(['name' => 'My Task'], $this->hierarchy['list'], $this->owner);
    $task->assign($this->owner);

    $myTasks = $this->service->getMyTasks($this->owner);

    expect($myTasks->pluck('id'))->toContain($task->id);
});

// getMySubtasks

test('getMySubtasks returns only subtasks assigned to user', function () {
    $task = $this->service->create(['name' => 'Parent'], $this->hierarchy['list'], $this->owner);

    $subtask = $this->createSubtask($task, ['name' => 'Assigned Sub']);
    $subtask->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $mySubtasks = $this->service->getMySubtasks($this->owner);

    expect($mySubtasks->pluck('id'))->toContain($subtask->id);
});

// applyFilters (pure query building, no DB result)

test('applyFilters adds whereIn for status_ids', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('whereIn')->once()->with('status_id', [1, 2])->andReturnSelf();

    $method = new ReflectionMethod(new \App\Services\TaskService(), 'applyFilters');
    $method->invoke(new \App\Services\TaskService(), $query, ['status_ids' => [1, 2]]);
});

test('applyFilters adds whereIn for priority_levels', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('whereIn')->once()->with('priority_level', [1])->andReturnSelf();

    $method = new ReflectionMethod(new \App\Services\TaskService(), 'applyFilters');
    $method->invoke(new \App\Services\TaskService(), $query, ['priority_levels' => [1]]);
});

test('applyFilters adds where clause for search', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
    $query->shouldReceive('where')->once()->with(Mockery::type('Closure'))->andReturnSelf();

    $method = new ReflectionMethod(new \App\Services\TaskService(), 'applyFilters');
    $method->invoke(new \App\Services\TaskService(), $query, ['search' => 'feature']);
});

test('applyFilters returns query unchanged for empty filters', function () {
    $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

    $method = new ReflectionMethod(new \App\Services\TaskService(), 'applyFilters');
    $result = $method->invoke(new \App\Services\TaskService(), $query, []);

    expect($result)->toBe($query);
});
