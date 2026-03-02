<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'subtask_id',
        'user_id',
        'parent_id',
        'content',
        'mentions',
        'attachments',
        'is_resolved',
        'edited_at',
    ];

    protected $casts = [
        'mentions' => 'array',
        'attachments' => 'array',
        'is_resolved' => 'boolean',
        'edited_at' => 'datetime',
    ];


    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }


    public function edit(string $content): void
    {
        $this->update([
            'content' => $content,
            'edited_at' => now(),
        ]);
    }

    public function resolve(): void
    {
        $this->update(['is_resolved' => true]);
    }

    public function unresolve(): void
    {
        $this->update(['is_resolved' => false]);
    }
}
