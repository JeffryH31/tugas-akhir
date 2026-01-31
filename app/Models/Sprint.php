<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sprint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'is_active',
        'position',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the space that owns the sprint.
     */
    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the subtasks for the sprint.
     */
    public function subtasks()
    {
        return $this->hasMany(Subtask::class);
    }

    /**
     * Scope for active sprints.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for upcoming sprints.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    /**
     * Scope for completed sprints.
     */
    public function scopeCompleted($query)
    {
        return $query->where('end_date', '<', now());
    }

    /**
     * Check if sprint is currently active (date range).
     */
    public function isInProgress(): bool
    {
        return now()->between($this->start_date, $this->end_date);
    }

    /**
     * Check if sprint is completed.
     */
    public function isCompleted(): bool
    {
        return now()->greaterThan($this->end_date);
    }

    /**
     * Get sprint duration in days.
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get remaining days in sprint.
     */
    public function getRemainingDays(): int
    {
        if ($this->isCompleted()) {
            return 0;
        }
        return now()->diffInDays($this->end_date, false);
    }
}
