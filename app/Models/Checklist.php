<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Checklist Model 
 *
 * @property int $id
 * @property string $name
 * @property int $task_id
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Checklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'task_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Get the task that owns the checklist.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get all items in the checklist.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    /**
     * Calculate checklist progress.
     */
    public function getProgressAttribute(): int
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->items()->where('is_completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get completed items count.
     */
    public function getCompletedCountAttribute(): int
    {
        return $this->items()->where('is_completed', true)->count();
    }

    /**
     * Get total items count.
     */
    public function getTotalCountAttribute(): int
    {
        return $this->items()->count();
    }
}
