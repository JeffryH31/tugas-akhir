<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TaskList Model
 *
 * Represents a List within a Space or Folder.
 * Lists contain Tasks and have their own statuses.
 *
 * Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 */
class TaskList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'task_lists';

    protected $fillable = [
        'name',
        'description',
        'space_id',
        'folder_id',
        'created_by',
        'color',
        'position',
        'is_archived',
        'is_active',
        'due_date',
        'priority',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_archived' => 'boolean',
        'is_active' => 'boolean',
        'due_date' => 'date',
        'priority' => 'integer',
    ];

    protected $attributes = [
        'color' => '#6366F1',
        'position' => 0,
        'is_archived' => false,
        'is_active' => true,
    ];

    /**
     * Get the space that owns the list.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the folder that contains the list (if any).
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the user who created the list.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the workspace through space.
     */
    public function workspace()
    {
        return $this->space->workspace;
    }

    /**
     * Get all tasks in the list.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id')->whereNull('parent_id')->orderBy('position');
    }

    /**
     * Get all tasks including subtasks.
     */
    public function allTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id')->orderBy('position');
    }

    /**
     * Get active tasks in the list.
     */
    public function activeTasks(): HasMany
    {
        return $this->tasks()->where('is_active', true);
    }

    /**
     * Get all statuses for this list.
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class, 'list_id')->orderBy('position');
    }

    /**
     * Get all labels attached to the list.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'list_label', 'list_id', 'label_id')
            ->withTimestamps();
    }

    /**
     * Get all assignees of the list.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_user', 'list_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get all views for this list.
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class, 'list_id');
    }

    /**
     * Get all automations for this list.
     */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class, 'list_id');
    }

    /**
     * Get all time entries for tasks in this list.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'list_id');
    }

    /**
     * Calculate progress based on completed tasks.
     */
    public function getProgressAttribute(): int
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }
        $completedTasks = $this->tasks()->where('is_completed', true)->count();
        return (int) round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Scope active lists.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope not archived lists.
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope lists without folder.
     */
    public function scopeWithoutFolder($query)
    {
        return $query->whereNull('folder_id');
    }
}
