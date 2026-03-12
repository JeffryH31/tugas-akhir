<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subtask_id',
        'task_id',
        'sprint_id',
        'status_id',
        'priority_level',
        'name',
        'description',
        'start_date',
        'due_date',
        'completed_at',
        'time_estimate',
        'time_spent',
        'position',
        'is_archived',
        'custom_fields',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'time_estimate' => 'integer',
        'time_spent' => 'integer',
        'is_archived' => 'boolean',
        'custom_fields' => 'array',
        'priority_level' => PriorityLevel::class,
    ];

    protected $with = ['status'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($subtask) {
            if (empty($subtask->subtask_id)) {
                $task = Task::find($subtask->task_id);
                $count = static::withTrashed()->where('task_id', $subtask->task_id)->count() + 1;
                $subtask->subtask_id = $task->task_id . '-' . $count;

                // Ensure uniqueness
                while (static::withTrashed()->where('subtask_id', $subtask->subtask_id)->exists()) {
                    $count++;
                    $subtask->subtask_id = $task->task_id . '-' . $count;
                }
            }

            if (is_null($subtask->position)) {
                $maxPosition = static::where('task_id', $subtask->task_id)
                    ->where('status_id', $subtask->status_id)
                    ->max('position');
                $subtask->position = $maxPosition !== null ? $maxPosition + 1 : 0;
            }
        });

        static::deleting(function ($subtask) {
            if ($subtask->isForceDeleting()) return;
            $subtask->timeEntries()->each(fn($entry) => $entry->delete());
        });

        static::restoring(function ($subtask) {
            $subtask->timeEntries()->onlyTrashed()->each(fn($entry) => $entry->restore());
        });
    }


    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function getPriorityAttribute(): ?array
    {
        return $this->priority_level?->toArray();
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subtask_assignees')
            ->withTimestamps()
            ->withPivot(['assigned_at', 'assigned_by']);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'subtask_labels')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Subtask::class, 'subtask_dependencies', 'subtask_id', 'depends_on_subtask_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Subtask::class, 'subtask_dependencies', 'depends_on_subtask_id', 'subtask_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }


    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereNull('completed_at')
            ->whereDate('due_date', today());
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }


    public function isOverdue(): bool
    {
        return !$this->completed_at && $this->due_date && $this->due_date->isPast();
    }

    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public function markAsCompleted(User $user): void
    {
        $this->update([
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);
    }

    public function markAsIncomplete(): void
    {
        $this->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }
}
