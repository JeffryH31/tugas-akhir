<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'task_list_id',
        'parent_id',
        'status_id',
        'priority_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'completed_at',
        'time_estimate',
        'time_spent',
        'position',
        'is_archived',
        'is_template',
        'custom_fields',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'is_archived' => 'boolean',
        'is_template' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected $appends = [
        'is_completed',
        'is_overdue',
        'progress',
    ];

    /**
     * Default eager loading relationships
     */
    protected $with = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($task) {
            // Generate human-readable task ID
            if (empty($task->task_id)) {
                $list = TaskList::with('space.workspace')->find($task->task_list_id);
                $prefix = strtoupper(substr($list->space->workspace->name, 0, 3));
                $count = Task::whereHas('taskList.space', function ($q) use ($list) {
                    $q->where('workspace_id', $list->space->workspace_id);
                })->count() + 1;
                $task->task_id = $prefix . '-' . $count;
            }

            // Set position
            if (empty($task->position)) {
                $task->position = static::where('task_list_id', $task->task_list_id)
                    ->where('parent_id', $task->parent_id)
                    ->max('position') + 1;
            }

            // Set default status
            if (empty($task->status_id)) {
                $list = TaskList::with('space')->find($task->task_list_id);
                $defaultStatus = $list->space->getDefaultStatus();
                $task->status_id = $defaultStatus?->id;
            }
        });

        // Update time_spent when time entries change
        static::saved(function ($task) {
            if ($task->wasChanged('completed_at') && $task->completed_at) {
                // Update parent progress if this is a subtask
                if ($task->parent) {
                    $task->parent->updateProgress();
                }
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_labels')->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withTimestamps();
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    // ==================== ACCESSORS ====================

    public function getIsCompletedAttribute(): bool
    {
        return $this->completed_at !== null;
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->is_completed) {
            return false;
        }
        return $this->due_date->isPast();
    }

    public function getProgressAttribute(): float
    {
        $subtasks = $this->subtasks;
        if ($subtasks->isEmpty()) {
            return $this->is_completed ? 100 : 0;
        }

        $completed = $subtasks->filter(fn($t) => $t->is_completed)->count();
        return round(($completed / $subtasks->count()) * 100, 1);
    }

    public function getTimeSpentFormattedAttribute(): string
    {
        $hours = floor($this->time_spent / 60);
        $minutes = $this->time_spent % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }

    public function getTimeEstimateFormattedAttribute(): string
    {
        if (!$this->time_estimate) return '-';
        
        $hours = floor($this->time_estimate / 60);
        $minutes = $this->time_estimate % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }

    // ==================== SCOPES ====================

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
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

    public function scopeDueSoon($query, int $days = 3)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    // ==================== HELPER METHODS ====================

    public function complete(?User $user = null): void
    {
        $this->update([
            'completed_at' => now(),
            'completed_by' => $user?->id,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function updateTimeSpent(): void
    {
        $totalMinutes = $this->timeEntries()->sum('duration');
        $this->update(['time_spent' => $totalMinutes]);
    }

    public function updateProgress(): void
    {
        // This method is called when subtask completion status changes
        // Progress is calculated dynamically via accessor
    }

    public function assign(User $user, ?User $assignedBy = null): void
    {
        $this->assignees()->syncWithoutDetaching([
            $user->id => [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy?->id,
            ]
        ]);
    }

    public function unassign(User $user): void
    {
        $this->assignees()->detach($user->id);
    }

    public function addLabel(Label $label): void
    {
        $this->labels()->syncWithoutDetaching($label->id);
    }

    public function removeLabel(Label $label): void
    {
        $this->labels()->detach($label->id);
    }

    public function move(TaskList $newList, ?int $position = null): void
    {
        $this->update([
            'task_list_id' => $newList->id,
            'position' => $position ?? Task::where('task_list_id', $newList->id)
                ->whereNull('parent_id')
                ->max('position') + 1,
        ]);
    }

    public function changeStatus(Status $status): void
    {
        $wasCompleted = $this->is_completed;
        
        $this->update(['status_id' => $status->id]);

        // Auto-complete if status is closed
        if ($status->is_closed && !$wasCompleted) {
            $this->complete();
        } elseif (!$status->is_closed && $wasCompleted) {
            $this->reopen();
        }
    }
}
