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
        'status_id',
        'priority_id',
        'name',
        'description',
        'position',
        'is_archived',
        'is_template',
        'custom_fields',
        'created_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'is_template' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected $appends = [
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
                    ->max('position') + 1;
            }

            // Set default status
            if (empty($task->status_id)) {
                $list = TaskList::with('space')->find($task->task_list_id);
                $defaultStatus = $list->space->getDefaultStatus();
                $task->status_id = $defaultStatus?->id;
            }
        });

        // Recalculate progress when subtasks change
        static::saved(function ($task) {
            // Progress is calculated from subtasks
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
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

    public function getProgressAttribute(): float
    {
        $subtasks = $this->subtasks;
        if ($subtasks->isEmpty()) {
            return 0;
        }

        $completed = $subtasks->filter(fn($t) => $t->isCompleted())->count();
        return round(($completed / $subtasks->count()) * 100, 1);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    // ==================== HELPER METHODS ====================

    public function updateProgress(): void
    {
        // Progress is calculated dynamically via accessor based on subtasks
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
                ->max('position') + 1,
        ]);
    }

    public function changeStatus(Status $status): void
    {
        $this->update(['status_id' => $status->id]);
    }
}
