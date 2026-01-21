<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Label Model 
 *
 * Represents a label/tag that can be attached to tasks and lists.
 * Labels belong to a Space and can be used across all lists/tasks in that space.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $space_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Label extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Default label colors (standard).
     */
    public const DEFAULT_COLORS = [
        '#6366F1', // Indigo
        '#10B981', // Green
        '#F59E0B', // Amber
        '#EF4444', // Red
        '#8B5CF6', // Purple
        '#EC4899', // Pink
        '#0EA5E9', // Sky
        '#6B7280', // Gray
        '#14B8A6', // Teal
        '#F97316', // Orange
    ];

    protected $fillable = [
        'name',
        'color',
        'space_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'color' => '#6366F1',
        'is_active' => true,
    ];

    /**
     * Get the space that owns this label.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get all lists that have this label.
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(TaskList::class, 'list_label', 'label_id', 'list_id')
            ->withTimestamps();
    }

    /**
     * Get all tasks that have this label.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label')
            ->withTimestamps();
    }

    /**
     * Scope active labels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the count of tasks using this label.
     */
    public function getUsageCount(): int
    {
        return $this->tasks()->count();
    }

    // ==========================================
    // Legacy aliases for backward compatibility
    // ==========================================

    /**
     * @deprecated Use space() instead
     */
    public function board(): BelongsTo
    {
        return $this->space();
    }

    /**
     * @deprecated Use lists() instead
     */
    public function features(): BelongsToMany
    {
        return $this->lists();
    }
}
