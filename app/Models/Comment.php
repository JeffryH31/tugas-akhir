<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comment Model 
 *
 * @property int $id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property int $user_id
 * @property string $content
 * @property int|null $parent_id
 * @property array|null $mentions
 * @property array|null $attachments
 * @property bool $is_resolved
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'content',
        'parent_id',
        'mentions',
        'attachments',
        'is_resolved',
    ];

    protected $casts = [
        'mentions' => 'array',
        'attachments' => 'array',
        'is_resolved' => 'boolean',
    ];

    protected $attributes = [
        'is_resolved' => false,
    ];

    /**
     * Get the commentable model (Task, List, etc.).
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who wrote the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get all replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Get mentioned users.
     */
    public function mentionedUsers()
    {
        if (empty($this->mentions)) {
            return collect();
        }
        return User::whereIn('id', $this->mentions)->get();
    }

    /**
     * Check if this is a reply.
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Mark comment as resolved.
     */
    public function resolve(): void
    {
        $this->update(['is_resolved' => true]);
    }

    /**
     * Mark comment as unresolved.
     */
    public function unresolve(): void
    {
        $this->update(['is_resolved' => false]);
    }

    /**
     * Scope to get only top-level comments.
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope resolved comments.
     */
    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope unresolved comments.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }
}
