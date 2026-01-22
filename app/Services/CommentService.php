<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommentService
{
    /**
     * Get comments for a task
     */
    public function getCommentsForTask(Task $task): Collection
    {
        return $task->comments()
            ->with([
                'user',
                'replies' => fn($q) => $q->with('user')->orderBy('created_at'),
                'reactions' => fn($q) => $q->with('user'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new comment
     */
    public function create(Task $task, User $user, array $data): Comment
    {
        return DB::transaction(function () use ($task, $user, $data) {
            $comment = Comment::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'parent_id' => $data['parent_id'] ?? null,
                'content' => $data['content'],
                'mentions' => $data['mentions'] ?? null,
                'attachments' => $data['attachments'] ?? null,
            ]);

            Activity::log($task->taskList->space->workspace, $user, $task, 'commented', [
                'name' => $task->name,
                'comment_preview' => substr($data['content'], 0, 100),
            ]);

            return $comment->load('user');
        });
    }

    /**
     * Update a comment
     */
    public function update(Comment $comment, array $data, User $user): Comment
    {
        $comment->edit($data['content']);
        
        return $comment->fresh('user');
    }

    /**
     * Delete a comment
     */
    public function delete(Comment $comment, User $user): void
    {
        DB::transaction(function () use ($comment, $user) {
            Activity::log($comment->task->taskList->space->workspace, $user, $comment->task, 'comment_deleted', [
                'name' => $comment->task->name,
            ]);

            $comment->delete();
        });
    }

    /**
     * Reply to a comment
     */
    public function reply(Comment $parentComment, User $user, array $data): Comment
    {
        return $this->create($parentComment->task, $user, [
            ...$data,
            'parent_id' => $parentComment->id,
        ]);
    }

    /**
     * Add reaction to comment
     */
    public function addReaction(Comment $comment, User $user, string $emoji): void
    {
        $comment->react($user, $emoji);
    }

    /**
     * Remove reaction from comment
     */
    public function removeReaction(Comment $comment, User $user, string $emoji): void
    {
        $comment->unreact($user, $emoji);
    }

    /**
     * Toggle reaction on comment
     */
    public function toggleReaction(Comment $comment, User $user, string $emoji): void
    {
        $existing = $comment->reactions()
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->exists();

        if ($existing) {
            $this->removeReaction($comment, $user, $emoji);
        } else {
            $this->addReaction($comment, $user, $emoji);
        }
    }

    /**
     * Resolve a comment
     */
    public function resolve(Comment $comment, User $user): Comment
    {
        $comment->resolve();

        Activity::log($comment->task->taskList->space->workspace, $user, $comment->task, 'comment_resolved', [
            'name' => $comment->task->name,
        ]);

        return $comment->fresh();
    }

    /**
     * Unresolve a comment
     */
    public function unresolve(Comment $comment, User $user): Comment
    {
        $comment->unresolve();

        return $comment->fresh();
    }

    /**
     * Extract mentioned users from comment content
     */
    public function extractMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);
        
        if (empty($matches[1])) {
            return [];
        }

        return User::whereIn('name', $matches[1])->pluck('id')->toArray();
    }
}
