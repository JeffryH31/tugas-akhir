<?php

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Task;
use App\Services\CommentService;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new CommentService();
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// ── create ────────────────────────────────────────────────────────────────────

test('create comment on task', function () {
    $task = $this->hierarchy['task'];

    $comment = $this->service->create($task, $this->owner, [
        'content' => 'This is a comment',
    ]);

    expect($comment)->toBeInstanceOf(Comment::class);
    expect($comment->content)->toBe('This is a comment');
    expect($comment->task_id)->toBe($task->id);
    expect($comment->user_id)->toBe($this->owner->id);
    expect($comment->parent_id)->toBeNull();
});

test('create comment logs commented activity', function () {
    $task = $this->hierarchy['task'];

    $this->service->create($task, $this->owner, [
        'content' => 'Hello world',
    ]);

    $activity = Activity::where('action', 'commented')
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['comment_preview'])->toBe('Hello world');
});

test('create comment with mentions and attachments', function () {
    $task = $this->hierarchy['task'];

    $comment = $this->service->create($task, $this->owner, [
        'content' => 'Hey @john',
        'mentions' => ['john'],
        'attachments' => ['file.pdf'],
    ]);

    expect($comment->mentions)->toBe(['john']);
    expect($comment->attachments)->toBe(['file.pdf']);
});

// ── update ────────────────────────────────────────────────────────────────────

test('update comment changes content', function () {
    $task = $this->hierarchy['task'];
    $comment = $this->service->create($task, $this->owner, ['content' => 'Original']);

    $updated = $this->service->update($comment, ['content' => 'Edited'], $this->owner);

    expect($updated->content)->toBe('Edited');
});

// ── delete ────────────────────────────────────────────────────────────────────

test('delete comment removes it and logs activity', function () {
    $task = $this->hierarchy['task'];
    $comment = $this->service->create($task, $this->owner, ['content' => 'To delete']);

    $this->service->delete($comment, $this->owner);

    expect(Comment::find($comment->id))->toBeNull();

    $activity = Activity::where('action', 'comment_deleted')
        ->where('subject_id', $task->id)
        ->first();
    expect($activity)->not->toBeNull();
});

// ── reply ─────────────────────────────────────────────────────────────────────

test('reply creates comment with parent_id', function () {
    $task = $this->hierarchy['task'];
    $parent = $this->service->create($task, $this->owner, ['content' => 'Parent comment']);

    $reply = $this->service->reply($parent, $this->owner, ['content' => 'Reply text']);

    expect($reply->parent_id)->toBe($parent->id);
    expect($reply->content)->toBe('Reply text');
    expect($reply->task_id)->toBe($task->id);
});

// ── resolve / unresolve ───────────────────────────────────────────────────────

test('resolve comment marks it as resolved', function () {
    $task = $this->hierarchy['task'];
    $comment = $this->service->create($task, $this->owner, ['content' => 'Resolve me']);

    $resolved = $this->service->resolve($comment, $this->owner);

    expect($resolved->is_resolved)->toBeTrue();
});

test('unresolve comment clears resolved state', function () {
    $task = $this->hierarchy['task'];
    $comment = $this->service->create($task, $this->owner, ['content' => 'Unresolve me']);
    $this->service->resolve($comment, $this->owner);

    $unresolved = $this->service->unresolve($comment->fresh(), $this->owner);

    expect($unresolved->is_resolved)->toBeFalse();
});

// ── extractMentions regex (pure logic, no DB) ─────────────────────────────────

test('extractMentions regex extracts single username', function () {
    preg_match_all('/@(\w+)/', 'Hello @john please review', $matches);
    expect($matches[1])->toBe(['john']);
});

test('extractMentions regex extracts multiple usernames', function () {
    preg_match_all('/@(\w+)/', '@alice and @bob cc @charlie', $matches);
    expect($matches[1])->toBe(['alice', 'bob', 'charlie']);
});

test('extractMentions regex returns empty for no mentions', function () {
    preg_match_all('/@(\w+)/', 'No mentions here', $matches);
    expect($matches[1])->toBe([]);
});

test('extractMentions regex handles underscore in usernames', function () {
    preg_match_all('/@(\w+)/', 'Hey @john_doe', $matches);
    expect($matches[1])->toBe(['john_doe']);
});
