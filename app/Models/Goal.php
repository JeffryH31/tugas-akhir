<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Goal Model 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $workspace_id
 * @property int $owner_id
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $status
 * @property int $progress
 * @property string $color
 * @property bool $is_private
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Goal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'workspace_id',
        'owner_id',
        'due_date',
        'status',
        'progress',
        'color',
        'is_private',
    ];

    protected $casts = [
        'due_date' => 'date',
        'progress' => 'integer',
        'is_private' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'on_track',
        'progress' => 0,
        'color' => '#6366F1',
        'is_private' => false,
    ];

    /**
     * Status constants.
     */
    public const STATUS_ON_TRACK = 'on_track';
    public const STATUS_AT_RISK = 'at_risk';
    public const STATUS_OFF_TRACK = 'off_track';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the workspace that owns the goal.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the owner of the goal.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all targets for this goal.
     */
    public function targets(): HasMany
    {
        return $this->hasMany(GoalTarget::class);
    }

    /**
     * Calculate overall progress from targets.
     */
    public function calculateProgress(): int
    {
        $targets = $this->targets;
        if ($targets->isEmpty()) {
            return 0;
        }

        $totalProgress = $targets->sum(function ($target) {
            return $target->progress;
        });

        return (int) round($totalProgress / $targets->count());
    }

    /**
     * Update progress based on targets.
     */
    public function updateProgress(): void
    {
        $this->update(['progress' => $this->calculateProgress()]);
    }

    /**
     * Check if goal is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== self::STATUS_COMPLETED;
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope public goals.
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }
}
