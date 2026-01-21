<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status Model 
 *
 * Represents a custom status for tasks within a List.
 * Each List can have its own set of statuses.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $list_id
 * @property string $type (open, in_progress, closed)
 * @property int $position
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'list_id',
        'type',
        'position',
        'is_default',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_default' => 'boolean',
    ];

    protected $attributes = [
        'color' => '#6B7280',
        'type' => 'open',
        'position' => 0,
        'is_default' => false,
    ];

    /**
     * Status types.
     */
    public const TYPE_OPEN = 'open';
    public const TYPE_IN_PROGRESS = 'in_progress';
    public const TYPE_CLOSED = 'closed';

    /**
     * Default statuses for a new list.
     */
    public const DEFAULT_STATUSES = [
        ['name' => 'To Do', 'color' => '#6B7280', 'type' => 'open', 'position' => 0, 'is_default' => true],
        ['name' => 'In Progress', 'color' => '#3B82F6', 'type' => 'in_progress', 'position' => 1],
        ['name' => 'Review', 'color' => '#F59E0B', 'type' => 'in_progress', 'position' => 2],
        ['name' => 'Complete', 'color' => '#10B981', 'type' => 'closed', 'position' => 3],
    ];

    /**
     * Get the list that owns the status.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    /**
     * Get all tasks with this status.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'status_id');
    }

    /**
     * Check if status is an "open" type.
     */
    public function isOpen(): bool
    {
        return $this->type === self::TYPE_OPEN;
    }

    /**
     * Check if status is "in progress" type.
     */
    public function isInProgress(): bool
    {
        return $this->type === self::TYPE_IN_PROGRESS;
    }

    /**
     * Check if status is "closed" type.
     */
    public function isClosed(): bool
    {
        return $this->type === self::TYPE_CLOSED;
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
