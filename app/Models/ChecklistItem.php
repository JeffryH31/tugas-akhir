<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChecklistItem Model 
 *
 * @property int $id
 * @property string $name
 * @property int $checklist_id
 * @property int|null $assignee_id
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'checklist_id',
        'assignee_id',
        'is_completed',
        'completed_at',
        'position',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    protected $attributes = [
        'is_completed' => false,
        'position' => 0,
    ];

    /**
     * Get the checklist that owns the item.
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /**
     * Get the task through checklist.
     */
    public function task()
    {
        return $this->checklist->task;
    }

    /**
     * Get the assignee of the item.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Mark item as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark item as incomplete.
     */
    public function markIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    /**
     * Toggle completion status.
     */
    public function toggleComplete(): void
    {
        if ($this->is_completed) {
            $this->markIncomplete();
        } else {
            $this->markCompleted();
        }
    }
}
