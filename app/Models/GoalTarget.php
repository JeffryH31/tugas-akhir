<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GoalTarget Model 
 *
 * @property int $id
 * @property string $name
 * @property int $goal_id
 * @property string $type
 * @property float $target_value
 * @property float $current_value
 * @property string|null $unit
 * @property int|null $linked_list_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GoalTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'goal_id',
        'type',
        'target_value',
        'current_value',
        'unit',
        'linked_list_id',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    protected $attributes = [
        'type' => 'number',
        'target_value' => 0,
        'current_value' => 0,
    ];

    /**
     * Target types.
     */
    public const TYPE_NUMBER = 'number';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_TASK_COMPLETION = 'task_completion';

    /**
     * Get the goal that owns the target.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Get the linked list (if any).
     */
    public function linkedList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'linked_list_id');
    }

    /**
     * Get progress percentage.
     */
    public function getProgressAttribute(): float
    {
        if ($this->type === self::TYPE_BOOLEAN) {
            return $this->current_value >= 1 ? 100 : 0;
        }

        if ($this->target_value <= 0) {
            return 0;
        }

        return min(100, ($this->current_value / $this->target_value) * 100);
    }

    /**
     * Update current value for task completion type.
     */
    public function updateTaskCompletionProgress(): void
    {
        if ($this->type !== self::TYPE_TASK_COMPLETION || !$this->linked_list_id) {
            return;
        }

        $list = $this->linkedList;
        if (!$list) {
            return;
        }

        $totalTasks = $list->tasks()->count();
        $completedTasks = $list->tasks()->where('is_completed', true)->count();

        $this->update([
            'target_value' => $totalTasks,
            'current_value' => $completedTasks,
        ]);

        $this->goal->updateProgress();
    }

    /**
     * Check if target is complete.
     */
    public function isComplete(): bool
    {
        return $this->progress >= 100;
    }
}
