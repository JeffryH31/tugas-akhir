<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task Model 
 *
 * Represents a task within a List. Tasks can have subtasks (parent_id).
 * 
 * Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $description
 * @property int $list_id
 * @property int|null $status_id
 * @property string $status
 * @property string $priority
 * @property int|null $assignee_id
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property bool $is_completed
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int $position
 * @property float $estimated_hours
 * @property float $actual_hours
 * @property array|null $custom_fields
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'title',
        'description',
        'list_id',
        'status_id',
        'priority',
        'created_by',
        'start_date',
        'due_date',
        'is_completed',
        'completed_at',
        'position',
        'estimated_hours',
        'actual_hours',
        'custom_fields',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'position' => 'integer',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'custom_fields' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'priority' => 'normal',
        'is_completed' => false,
        'is_active' => true,
        'estimated_hours' => 0,
        'actual_hours' => 0,
    ];

    /**
     * Priority levels (standard).
     */
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_LOW = 'low';

    public const PRIORITIES = [
        self::PRIORITY_URGENT,
        self::PRIORITY_HIGH,
        self::PRIORITY_NORMAL,
        self::PRIORITY_LOW,
    ];

    /**
     * Legacy status options.
     */
    public const STATUSES = ['todo', 'in-progress', 'review', 'done'];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the parent task (if this is a subtask).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get all subtasks.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get the list that contains this task.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    /**
     * Get the custom status for this task.
     */
    public function statusModel(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * Get all assignees (multiple assignees support).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withTimestamps();
    }

    /**
     * Get the primary assignee (first assignee).
     */
    public function assignee(): BelongsTo
    {
        // Return first assignee as primary - this is a virtual relationship
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    /**
     * Get primary assignee from the pivot table.
     */
    public function getPrimaryAssigneeAttribute(): ?User
    {
        return $this->assignees->first();
    }

    /**
     * Get the user who created this task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all time entries for this task.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('work_date');
    }

    /**
     * Get all checklists in this task.
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    /**
     * Get all comments on this task.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    /**
     * Get all attachments on this task.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get all labels attached to this task.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'task_label')
            ->withTimestamps();
    }

    /**
     * Get all watchers of this task.
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')
            ->withTimestamps();
    }

    /**
     * Get tasks that this task depends on.
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    /**
     * Get tasks that are waiting on this task.
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_id');
    }

    /**
     * Get custom field values.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(TaskCustomFieldValue::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->whereHas('assignees', fn($q) => $q->where('users.id', $userId));
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->where('is_completed', false)
            ->where('due_date', '<', now()->startOfDay());
    }

    public function scopeParentTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Check if this is a subtask.
     */
    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if the task has subtasks.
     */
    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    /**
     * Mark the task as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
            'status' => 'done',
        ]);
    }

    /**
     * Mark the task as incomplete.
     */
    public function markAsIncomplete(): void
    {
        $this->update([
            'is_completed' => false,
            'completed_at' => null,
            'status' => 'todo',
        ]);
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && !$this->is_completed
            && $this->due_date->isPast();
    }

    /**
     * Calculate subtasks progress.
     */
    public function getSubtasksProgressAttribute(): int
    {
        $total = $this->subtasks()->count();
        if ($total === 0) {
            return 0;
        }
        $completed = $this->subtasks()->where('is_completed', true)->count();
        return (int) round(($completed / $total) * 100);
    }

    /**
     * Calculate checklists progress.
     */
    public function getChecklistsProgressAttribute(): int
    {
        $checklists = $this->checklists()->with('items')->get();
        $totalItems = 0;
        $completedItems = 0;

        foreach ($checklists as $checklist) {
            $totalItems += $checklist->items->count();
            $completedItems += $checklist->items->where('is_completed', true)->count();
        }

        if ($totalItems === 0) {
            return 0;
        }

        return (int) round(($completedItems / $totalItems) * 100);
    }

    /**
     * Get time progress percentage.
     */
    public function getTimeProgressPercentage(): int
    {
        if ($this->estimated_hours <= 0) {
            return 0;
        }
        return min(100, (int) round(($this->actual_hours / $this->estimated_hours) * 100));
    }

    /**
     * Check if actual hours exceed estimated hours.
     */
    public function isOverBudget(): bool
    {
        return $this->estimated_hours > 0 && $this->actual_hours > $this->estimated_hours;
    }

    /**
     * Get remaining hours.
     */
    public function getRemainingHours(): float
    {
        return max(0, $this->estimated_hours - $this->actual_hours);
    }

    /**
     * Add logged hours to the task.
     */
    public function addActualHours(float $hours): void
    {
        $this->increment('actual_hours', $hours);
    }

    /**
     * Get the space this task belongs to.
     */
    public function getSpace(): ?Space
    {
        return $this->list?->space;
    }

    /**
     * Get the workspace this task belongs to.
     */
    public function getWorkspace(): ?Workspace
    {
        return $this->getSpace()?->workspace;
    }

    // ==========================================
    // Legacy aliases for backward compatibility
    // ==========================================

    /**
     * @deprecated Use list() instead
     */
    public function taskList(): BelongsTo
    {
        return $this->list();
    }

    /**
     * @deprecated Use getSpace() instead
     */
    public function getBoard(): ?Space
    {
        return $this->getSpace();
    }

    /**
     * @deprecated Use list->folder->... instead
     */
    public function getFeature()
    {
        return $this->list;
    }
}
