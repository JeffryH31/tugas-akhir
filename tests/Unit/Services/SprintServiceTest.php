<?php

use App\Models\Sprint;
use App\Models\Subtask;
use App\Services\SprintService;
use Illuminate\Validation\ValidationException;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SprintService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// createSprint

test('createSprint creates sprint for list', function () {
    $sprint = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 1',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-14',
    ]);

    expect($sprint)->toBeInstanceOf(Sprint::class);
    expect($sprint->name)->toBe('Sprint 1');
    expect($sprint->task_list_id)->toBe($this->hierarchy['list']->id);
    expect($sprint->is_active)->toBeFalse();
});

test('createSprint with goal and active state', function () {
    $sprint = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint Active',
        'goal' => 'Complete feature X',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-14',
        'is_active' => true,
    ]);

    expect($sprint->goal)->toBe('Complete feature X');
    expect($sprint->is_active)->toBeTrue();
});

// updateSprint

test('updateSprint changes sprint name', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    $updated = $this->service->updateSprint($sprint, ['name' => 'Renamed Sprint']);

    expect($updated->name)->toBe('Renamed Sprint');
});

// deleteSprint

test('deleteSprint removes sprint and unlinks subtasks', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);

    $this->service->deleteSprint($sprint);

    expect(Sprint::find($sprint->id))->toBeNull();
    expect($subtask->fresh()->sprint_id)->toBeNull();
});

// startSprint / completeSprint

test('startSprint activates sprint and deactivates others', function () {
    $sprint1 = $this->createSprint($this->hierarchy['list'], ['is_active' => true]);
    $sprint2 = $this->createSprint($this->hierarchy['list']);

    $this->service->startSprint($sprint2);

    expect($sprint2->fresh()->is_active)->toBeTrue();
    expect($sprint1->fresh()->is_active)->toBeFalse();
});

test('completeSprint deactivates sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list'], ['is_active' => true]);

    $completed = $this->service->completeSprint($sprint);

    expect($completed->is_active)->toBeFalse();
});

// addSubtaskToSprint / removeSubtaskFromSprint

test('addSubtaskToSprint links subtask to sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->addSubtaskToSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBe($sprint->id);
});

test('addSubtaskToSprint throws for subtask not in product', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    // Create subtask in different product
    $otherHierarchy = $this->createFullHierarchy($this->owner, 'B');
    $otherSubtask = $this->createSubtask($otherHierarchy['task']);

    expect(fn() => $this->service->addSubtaskToSprint($sprint, $otherSubtask->id))
        ->toThrow(ValidationException::class);
});

test('removeSubtaskFromSprint unlinks subtask', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);

    $this->service->removeSubtaskFromSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBeNull();
});

// getSprintStatistics

test('getSprintStatistics returns correct counts', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id, 'completed_at' => now()]);

    $stats = $this->service->getSprintStatistics($sprint);

    expect($stats['total_subtasks'])->toBe(2);
    expect($stats['completed_subtasks'])->toBe(1);
    expect($stats['completion_rate'])->toEqual(50);
});

// getBacklogSubtasks

test('getBacklogSubtasks returns subtasks without sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $inSprint = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);
    $backlog = $this->createSubtask($this->hierarchy['task'], ['name' => 'Backlog Item']);

    $result = $this->service->getBacklogSubtasks($this->hierarchy['list']);

    expect($result->pluck('id'))->toContain($backlog->id);
    expect($result->pluck('id'))->not->toContain($inSprint->id);
});

// Pure logic: burndown & velocity calculation

test('ideal burndown decreases linearly from total to 0', function () {
    $total = 10;
    $days = 5;
    $burndown = [];
    for ($day = 0; $day <= $days; $day++) {
        $burndown[] = $total - ($total * $day / $days);
    }

    expect($burndown[0])->toEqual(10);
    expect($burndown[5])->toEqual(0);
    expect($burndown[1])->toEqual(8);
});

test('completion rate is 0 when no subtasks', function () {
    $rate = 0 > 0 ? round((0 / 0) * 100) : 0;
    expect($rate)->toBe(0);
});

test('completion rate is 100 when all completed', function () {
    $rate = 8 > 0 ? round((8 / 8) * 100) : 0;
    expect($rate)->toEqual(100);
});

test('average velocity calculation', function () {
    $data = [
        ['completed_subtasks' => 10],
        ['completed_subtasks' => 8],
        ['completed_subtasks' => 12],
    ];
    $avg = collect($data)->avg('completed_subtasks');
    expect(round($avg, 1))->toBe(10.0);
});
