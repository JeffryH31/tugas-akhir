<?php

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Status;
use App\Models\Task;
use App\Models\Project;
use App\Services\TaskService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TaskService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create task with name and description', function () {
    $task = $this->service->create([
        'name' => 'New Feature',
        'description' => 'Build the new feature',
    ], $this->hierarchy['list'], $this->owner);

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->name)->toBe('New Feature');
    expect($task->description)->toBe('Build the new feature');
    expect($task->project_id)->toBe($this->hierarchy['list']->id);
});

test('create task logs created activity', function () {
    $this->service->create([
        'name' => 'Activity Test',
    ], $this->hierarchy['list'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Task::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Activity Test');
});

test('delete task soft deletes and logs activity', function () {
    $task = $this->service->create([
        'name' => 'To Delete',
    ], $this->hierarchy['list'], $this->owner);

    $this->service->delete($task, $this->owner);

    expect(Task::find($task->id))->toBeNull();
    expect(Task::withTrashed()->find($task->id))->not->toBeNull();

    $activity = Activity::where('action', 'deleted')
        ->where('subject_type', Task::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('changeStatus updates status and logs activity', function () {
    $task = $this->service->create([
        'name' => 'Status Test',
    ], $this->hierarchy['list'], $this->owner);

    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $updated = $this->service->changeStatus($task, $inProgress, $this->owner);

    expect($updated->status_id)->toBe($inProgress->id);

    $activity = Activity::where('action', 'status_changed')->first();
    expect($activity)->not->toBeNull();
});

test('changePriority updates priority and logs activity', function () {
    $task = $this->service->create([
        'name' => 'Priority Test',
    ], $this->hierarchy['list'], $this->owner);

    $updated = $this->service->changePriority($task, PriorityLevel::High->value, $this->owner);

    expect($updated->priority_level)->toBe(PriorityLevel::High);

    $activity = Activity::where('action', 'priority_changed')->first();
    expect($activity)->not->toBeNull();
});

test('assign and unassign user to task', function () {
    $task = $this->service->create([
        'name' => 'Assignment Test',
    ], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();

    $assigned = $this->service->assign($task, $assignee, $this->owner);
    expect($assigned->assignees->pluck('id')->toArray())->toContain($assignee->id);

    $unassigned = $this->service->unassign($task->fresh(), $assignee, $this->owner);
    expect($unassigned->assignees->pluck('id')->toArray())->not->toContain($assignee->id);
});

test('assign logs assigned activity', function () {
    $task = $this->service->create([
        'name' => 'Assign Log Test',
    ], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();
    $this->service->assign($task, $assignee, $this->owner);

    $activity = Activity::where('action', 'assigned')->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['assignee_name'])->toBe($assignee->name);
});

test('move task to different list', function () {
    $task = $this->service->create([
        'name' => 'Movable Task',
    ], $this->hierarchy['list'], $this->owner);

    $newList = Project::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'New List',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->move($task, $newList, $this->owner);

    expect($moved->project_id)->toBe($newList->id);

    $activity = Activity::where('action', 'moved')
        ->where('subject_type', Task::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('reorder updates task positions', function () {
    $task1 = $this->service->create(['name' => 'Task 1'], $this->hierarchy['list'], $this->owner);
    $task2 = $this->service->create(['name' => 'Task 2'], $this->hierarchy['list'], $this->owner);

    $this->service->reorder($this->hierarchy['list'], [$task2->id, $task1->id]);

    expect($task2->fresh()->position)->toBe(0);
    expect($task1->fresh()->position)->toBe(1);
});

test('addLabel and removeLabel on task', function () {
    $task = $this->service->create(['name' => 'Label Test'], $this->hierarchy['list'], $this->owner);
    $label = $this->createLabel($this->hierarchy['workspace']);

    $labeled = $this->service->addLabel($task, $label, $this->owner);
    expect($labeled->labels->pluck('id')->toArray())->toContain($label->id);

    $unlabeled = $this->service->removeLabel($task->fresh(), $label, $this->owner);
    expect($unlabeled->labels->pluck('id')->toArray())->not->toContain($label->id);
});

test('duplicate task copies name, assignees, labels, and subtasks', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();
    $task->assign($assignee);

    $label = $this->createLabel($this->hierarchy['workspace']);
    $task->addLabel($label);

    $this->createSubtask($task, ['name' => 'Sub A']);

    // Refresh to pick up relationships added outside Eloquent's cache
    $task = $task->fresh(['assignees', 'labels', 'subtasks']);

    $duplicate = $this->service->duplicate($task, $this->owner);

    expect($duplicate->name)->toBe('Original (Copy)');
    expect($duplicate->assignees)->toHaveCount(1);
    expect($duplicate->labels)->toHaveCount(1);
    expect($duplicate->subtasks)->toHaveCount(1);
});

test('getTasksForList returns tasks with relations', function () {
    $this->service->create(['name' => 'Task A'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Task B'], $this->hierarchy['list'], $this->owner);

    $tasks = $this->service->getTasksForList($this->hierarchy['list']);

    // +1 for the task created in createFullHierarchy
    expect($tasks->count())->toBeGreaterThanOrEqual(2);
});

test('getTasksForList applies search filter', function () {
    $this->service->create(['name' => 'Alpha Feature'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Beta Bug'], $this->hierarchy['list'], $this->owner);

    $tasks = $this->service->getTasksForList($this->hierarchy['list'], ['search' => 'Alpha']);

    expect($tasks->count())->toBe(1);
    expect($tasks->first()->name)->toBe('Alpha Feature');
});

test('getMyTasks returns tasks assigned to user', function () {
    $task = $this->service->create(['name' => 'My Task'], $this->hierarchy['list'], $this->owner);
    $task->assign($this->owner);

    $myTasks = $this->service->getMyTasks($this->owner);

    expect($myTasks->pluck('id')->toArray())->toContain($task->id);
});

test('getMySubtasks returns only subtasks assigned to user', function () {
    $task = $this->service->create(['name' => 'Parent Task'], $this->hierarchy['list'], $this->owner);
    // Task-level assignee but NO subtask-level assignee -> should NOT appear
    $task->assign($this->owner);

    $subtaskAssigned = $this->createSubtask($task, ['name' => 'Assigned Sub']);
    $subtaskAssigned->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $subtaskUnassigned = $this->createSubtask($task, ['name' => 'Unassigned Sub']);

    $mySubtasks = $this->service->getMySubtasks($this->owner);

    expect($mySubtasks->pluck('id')->toArray())->toContain($subtaskAssigned->id);
    expect($mySubtasks->pluck('id')->toArray())->not->toContain($subtaskUnassigned->id);
});

test('getMySubtasks excludes completed subtasks', function () {
    $task = $this->service->create(['name' => 'Parent'], $this->hierarchy['list'], $this->owner);

    $active = $this->createSubtask($task, ['name' => 'Active']);
    $active->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $done = $this->createSubtask($task, ['name' => 'Done', 'completed_at' => now()]);
    $done->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $mySubtasks = $this->service->getMySubtasks($this->owner);

    expect($mySubtasks->pluck('id')->toArray())->toContain($active->id);
    expect($mySubtasks->pluck('id')->toArray())->not->toContain($done->id);
});

test('getMySubtasks with is_overdue filter returns only overdue subtasks', function () {
    $task = $this->service->create(['name' => 'Parent'], $this->hierarchy['list'], $this->owner);

    $overdue = $this->createSubtask($task, [
        'name' => 'Overdue',
        'due_date' => now()->subDays(3),
    ]);
    $overdue->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $future = $this->createSubtask($task, [
        'name' => 'Future',
        'due_date' => now()->addDays(5),
    ]);
    $future->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $noDue = $this->createSubtask($task, ['name' => 'No Due']);
    $noDue->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $overdueSubtasks = $this->service->getMySubtasks($this->owner, ['is_overdue' => true]);

    expect($overdueSubtasks->pluck('id')->toArray())->toContain($overdue->id);
    expect($overdueSubtasks->pluck('id')->toArray())->not->toContain($future->id);
    expect($overdueSubtasks->pluck('id')->toArray())->not->toContain($noDue->id);
});

test('getTaskWithRelations loads all expected relations', function () {
    $task = $this->hierarchy['task'];

    // Add a subtask so the subtasks relation is non-empty
    $this->createSubtask($task, ['name' => 'Sub 1']);

    $loaded = $this->service->getTaskWithRelations($task);

    expect($loaded->relationLoaded('project'))->toBeTrue();
    expect($loaded->relationLoaded('status'))->toBeTrue();
    expect($loaded->relationLoaded('assignees'))->toBeTrue();
    expect($loaded->relationLoaded('labels'))->toBeTrue();
    expect($loaded->relationLoaded('subtasks'))->toBeTrue();
    expect($loaded->relationLoaded('dependencies'))->toBeTrue();
    expect($loaded->relationLoaded('dependents'))->toBeTrue();
    expect($loaded->relationLoaded('comments'))->toBeTrue();
    expect($loaded->relationLoaded('activities'))->toBeTrue();
    expect($loaded->relationLoaded('attachments'))->toBeTrue();
    expect($loaded->relationLoaded('creator'))->toBeTrue();
    expect($loaded->project->relationLoaded('space'))->toBeTrue();
    expect($loaded->subtasks)->toHaveCount(1);
});
