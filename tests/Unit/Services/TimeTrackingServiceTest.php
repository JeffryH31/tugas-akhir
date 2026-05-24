<?php

use App\Models\Activity;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TimeTrackingService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->hierarchy['task']);
});

// ── logTime ───────────────────────────────────────────────────────────────────

test('logTime creates time entry with duration', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 60,
    ]);

    expect($entry)->toBeInstanceOf(TimeEntry::class);
    expect($entry->duration)->toBe(60);
    expect($entry->subtask_id)->toBe($this->subtask->id);
    expect($entry->user_id)->toBe($this->owner->id);
});

test('logTime logs activity', function () {
    $this->service->logTime($this->subtask, $this->owner, ['duration' => 30]);

    $activity = Activity::where('action', 'time_logged')->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['duration'])->toBe(30);
});

test('logTime calculates duration from start and end times', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, [
        'started_at' => '2026-06-01 10:00:00',
        'ended_at' => '2026-06-01 11:30:00',
    ]);

    expect($entry->duration)->toBe(90);
});

// ── startTimer / stopTimer ────────────────────────────────────────────────────

test('startTimer creates running time entry', function () {
    $entry = $this->service->startTimer($this->subtask, $this->owner);

    expect($entry->is_running)->toBeTrue();
    expect($entry->subtask_id)->toBe($this->subtask->id);
});

test('stopTimer stops running entry and sets duration', function () {
    $entry = $this->service->startTimer($this->subtask, $this->owner);

    $stopped = $this->service->stopTimer($entry, $this->owner);

    expect($stopped->is_running)->toBeFalse();
    expect($stopped->ended_at)->not->toBeNull();
});

test('startTimer stops previous running timer', function () {
    $entry1 = $this->service->startTimer($this->subtask, $this->owner);
    $subtask2 = $this->createSubtask($this->hierarchy['task'], ['name' => 'Another']);
    $entry2 = $this->service->startTimer($subtask2, $this->owner);

    expect($entry1->fresh()->is_running)->toBeFalse();
    expect($entry2->is_running)->toBeTrue();
});

// ── getRunningTimer ───────────────────────────────────────────────────────────

test('getRunningTimer returns active timer', function () {
    $this->service->startTimer($this->subtask, $this->owner);

    $timer = $this->service->getRunningTimer($this->owner);

    expect($timer)->not->toBeNull();
    expect($timer->is_running)->toBeTrue();
});

test('getRunningTimer returns null when no timer running', function () {
    $timer = $this->service->getRunningTimer($this->owner);

    expect($timer)->toBeNull();
});

// ── updateEntry ───────────────────────────────────────────────────────────────

test('updateEntry changes duration and logs activity', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 30]);

    $updated = $this->service->updateEntry($entry, ['duration' => 60], $this->owner);

    expect($updated->duration)->toBe(60);

    $activity = Activity::where('action', 'time_updated')->first();
    expect($activity)->not->toBeNull();
});

// ── deleteEntry ───────────────────────────────────────────────────────────────

test('deleteEntry removes entry and logs activity', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 45]);

    $this->service->deleteEntry($entry, $this->owner);

    expect(TimeEntry::find($entry->id))->toBeNull();

    $activity = Activity::where('action', 'time_deleted')->first();
    expect($activity)->not->toBeNull();
});

// ── formatMinutes (pure logic, no DB) ────────────────────────────────────────

test('formatMinutes returns only minutes when less than 60', function () {
    $service = new \App\Services\TimeTrackingService();
    $method = new ReflectionMethod($service, 'formatMinutes');

    expect($method->invoke($service, 0))->toBe('0m');
    expect($method->invoke($service, 30))->toBe('30m');
    expect($method->invoke($service, 59))->toBe('59m');
});

test('formatMinutes returns hours and minutes when 60 or more', function () {
    $service = new \App\Services\TimeTrackingService();
    $method = new ReflectionMethod($service, 'formatMinutes');

    expect($method->invoke($service, 60))->toBe('1h 0m');
    expect($method->invoke($service, 90))->toBe('1h 30m');
    expect($method->invoke($service, 120))->toBe('2h 0m');
});
