<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'project_id',
        'status_id',
        'priority_level',
        'name',
        'description',
        'start_date',
        'due_date',
        'time_estimate',
        'position',
        'is_archived',
        'created_by',
    ];

    protected $casts = [
        'is_archived'           => 'boolean',
        'priority_level'        => PriorityLevel::class,
        'start_date' => 'date:Y-m-d',
        'due_date'   => 'date:Y-m-d',
    ];

    protected $appends = [
        'progress',
        'time_spent',
    ];

    /**
     * Default eager loading relationships
     */
    protected $with = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($task) {
            if (empty($task->task_id)) {
                $list = Project::with('space.workspace')->find($task->project_id);
                $prefix = strtoupper(substr($list->space->workspace->name, 0, 3));
                $count = Task::withTrashed()->whereHas('project.space', function ($q) use ($list) {
                    $q->where('workspace_id', $list->space->workspace_id);
                })->count() + 1;
                $task->task_id = $prefix . '-' . $count;

                // Ensure uniqueness
                while (Task::withTrashed()->where('task_id', $task->task_id)->exists()) {
                    $count++;
                    $task->task_id = $prefix . '-' . $count;
                }
            }

            if (empty($task->position)) {
                $task->position = static::where('project_id', $task->project_id)
                    ->max('position') + 1;
            }

            if (empty($task->status_id)) {
                $list = Project::with('space')->find($task->project_id);
                $defaultStatus = $list->space->getDefaultStatus();
                $task->status_id = $defaultStatus?->id;
            }
        });

        static::saved(function ($task) {
        });

        static::deleting(function ($task) {
            if ($task->isForceDeleting()) return;
            $task->subtasks()->each(fn($subtask) => $subtask->delete());
        });

        static::restoring(function ($task) {
            $task->subtasks()->onlyTrashed()->each(fn($subtask) => $subtask->restore());
        });
    }


    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function getPriorityAttribute(): ?array
    {
        return $this->priority_level?->toArray();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }



    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_by');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_labels')->withTimestamps();
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function timeEntries(): HasManyThrough
    {
        return $this->hasManyThrough(TimeEntry::class, Subtask::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getProgressAttribute(): float
    {
        // Use loaded subtasks if available, otherwise use count queries to avoid N+1
        if ($this->relationLoaded('subtasks')) {
            $subtasks = $this->subtasks;
            if ($subtasks->isEmpty()) return 0;
            $completed = $subtasks->filter(fn($t) => $t->isCompleted())->count();
            return round(($completed / $subtasks->count()) * 100, 1);
        }

        $total = $this->subtasks()->count();
        if ($total === 0) return 0;
        $completed = $this->subtasks()->whereNotNull('completed_at')->count();
        return round(($completed / $total) * 100, 1);
    }


    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }


    public function updateProgress(): void
    {
    }

    /**
     * Get total time spent across all subtasks (in minutes).
     * Computed from subtask time_spent fields (which aggregate time_entries.duration).
     */
    public function getTimeSpentAttribute(): int
    {
        if ($this->relationLoaded('subtasks')) {
            return (int) $this->subtasks->sum('time_spent');
        }

        return (int) $this->subtasks()->sum('time_spent');
    }

    public function assign(User $user, ?User $assignedBy = null): void
    {
        $this->assignees()->syncWithoutDetaching([
            $user->id => [
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

    public function move(Project $newList, ?int $position = null): void
    {
        $this->update([
            'project_id' => $newList->id,
            'position' => $position ?? Task::where('project_id', $newList->id)
                ->max('position') + 1,
        ]);
    }

    public function changeStatus(Status $status): void
    {
        $this->update(['status_id' => $status->id]);
    }
}
