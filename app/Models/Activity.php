<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity Model 
 *
 * Represents a logged activity in the system for audit trail and activity feed.
 * Uses polymorphic relationship to track activities on various models.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array|null $properties
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read User $user
 * @property-read Model|null $subject
 */
class Activity extends Model
{
    use HasFactory;

    /**
     * Activity types constants .
     */
    public const TYPE_TIME_LOGGED = 'time_logged';
    public const TYPE_TASK_COMPLETED = 'task_completed';
    public const TYPE_TASK_CREATED = 'task_created';
    public const TYPE_TASK_MOVED = 'task_moved';
    public const TYPE_TASK_ASSIGNED = 'task_assigned';
    public const TYPE_ESTIMATION_UPDATED = 'estimation_updated';
    public const TYPE_SPACE_CREATED = 'space_created';
    public const TYPE_LIST_CREATED = 'list_created';
    public const TYPE_STATUS_CHANGED = 'status_changed';
    public const TYPE_MEMBER_ADDED = 'member_added';
    public const TYPE_COMMENT_ADDED = 'comment_added';
    public const TYPE_SUBTASK_CREATED = 'subtask_created';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'subject_type',
        'subject_id',
        'properties',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the user who performed the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity (polymorphic).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to filter by activity type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to get activities by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to get activities for a specific subject.
     */
    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope a query to get recent activities.
     */
    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    /**
     * Log an activity for a task.
     */
    public static function logForTask(
        User $user,
        string $type,
        Task $task,
        array $properties = [],
        ?string $description = null
    ): static {
        return static::create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'properties' => array_merge([
                'task_title' => $task->title,
                'list_name' => $task->list?->name,
                'space_name' => $task->list?->space?->name,
            ], $properties),
            'description' => $description,
        ]);
    }

    /**
     * Log an activity for a space.
     */
    public static function logForSpace(
        User $user,
        string $type,
        Space $space,
        array $properties = [],
        ?string $description = null
    ): static {
        return static::create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => Space::class,
            'subject_id' => $space->id,
            'properties' => array_merge([
                'space_name' => $space->name,
                'workspace_name' => $space->workspace?->name,
            ], $properties),
            'description' => $description,
        ]);
    }

    /**
     * Log an activity for a list.
     */
    public static function logForList(
        User $user,
        string $type,
        TaskList $list,
        array $properties = [],
        ?string $description = null
    ): static {
        return static::create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => TaskList::class,
            'subject_id' => $list->id,
            'properties' => array_merge([
                'list_name' => $list->name,
                'space_name' => $list->space?->name,
            ], $properties),
            'description' => $description,
        ]);
    }

    /**
     * Get a human-readable description of the activity.
     */
    public function getDescriptionText(): string
    {
        $properties = $this->properties ?? $this->metadata ?? [];
        $type = $this->type ?? $this->action;

        return match ($type) {
            self::TYPE_TIME_LOGGED, 'time_logged' => sprintf(
                'logged %.2f hours on "%s"',
                $properties['hours'] ?? 0,
                $properties['task_title'] ?? 'Unknown task'
            ),
            self::TYPE_TASK_COMPLETED => sprintf(
                'completed "%s"',
                $properties['task_title'] ?? 'Unknown task'
            ),
            self::TYPE_TASK_CREATED => sprintf(
                'created task "%s"',
                $properties['task_title'] ?? 'Unknown task'
            ),
            self::TYPE_TASK_MOVED => sprintf(
                'moved "%s" from %s to %s',
                $properties['task_title'] ?? 'Unknown task',
                $properties['from_list'] ?? 'Unknown',
                $properties['to_list'] ?? 'Unknown'
            ),
            self::TYPE_TASK_ASSIGNED => sprintf(
                'assigned "%s" to %s',
                $properties['task_title'] ?? 'Unknown task',
                $properties['assignee_name'] ?? 'Unknown'
            ),
            self::TYPE_ESTIMATION_UPDATED => sprintf(
                'updated estimation for "%s" (%.1fh → %.1fh)',
                $properties['task_title'] ?? 'Unknown task',
                $properties['old_estimate'] ?? 0,
                $properties['new_estimate'] ?? 0
            ),
            self::TYPE_SPACE_CREATED => sprintf(
                'created space "%s"',
                $properties['space_name'] ?? 'Unknown space'
            ),
            self::TYPE_LIST_CREATED => sprintf(
                'created list "%s"',
                $properties['list_name'] ?? 'Unknown list'
            ),
            self::TYPE_STATUS_CHANGED => sprintf(
                'changed status of "%s" to %s',
                $properties['task_title'] ?? 'Unknown task',
                $properties['new_status'] ?? 'Unknown'
            ),
            self::TYPE_SUBTASK_CREATED => sprintf(
                'created subtask "%s"',
                $properties['task_title'] ?? 'Unknown subtask'
            ),
            default => $this->description ?? 'performed an action',
        };
    }

    /**
     * Get the icon for this activity type.
     */
    public function getIcon(): string
    {
        $type = $this->type ?? $this->action;

        return match ($type) {
            self::TYPE_TIME_LOGGED, 'time_logged' => 'mdi-clock-outline',
            self::TYPE_TASK_COMPLETED, 'task_completed', 'task.completed' => 'mdi-check-circle',
            self::TYPE_TASK_CREATED, 'task.created' => 'mdi-plus-circle',
            self::TYPE_TASK_MOVED, 'task.moved' => 'mdi-arrow-right',
            self::TYPE_TASK_ASSIGNED, 'task.assigned' => 'mdi-account-plus',
            self::TYPE_ESTIMATION_UPDATED, 'estimation.updated' => 'mdi-timer-edit',
            self::TYPE_SPACE_CREATED => 'mdi-folder-plus',
            self::TYPE_LIST_CREATED => 'mdi-format-list-bulleted-square',
            self::TYPE_STATUS_CHANGED => 'mdi-swap-horizontal',
            self::TYPE_SUBTASK_CREATED => 'mdi-checkbox-marked-outline',
            self::TYPE_MEMBER_ADDED => 'mdi-account-multiple-plus',
            self::TYPE_COMMENT_ADDED => 'mdi-comment-plus',
            default => 'mdi-information',
        };
    }

    /**
     * Get the color for this activity type.
     */
    public function getColor(): string
    {
        $type = $this->type ?? $this->action;

        return match ($type) {
            self::TYPE_TIME_LOGGED, 'time_logged' => 'primary',
            self::TYPE_TASK_COMPLETED, 'task_completed', 'task.completed' => 'success',
            self::TYPE_TASK_CREATED, 'task.created' => 'secondary',
            self::TYPE_TASK_MOVED, 'task.moved' => 'info',
            self::TYPE_TASK_ASSIGNED, 'task.assigned' => 'warning',
            self::TYPE_ESTIMATION_UPDATED, 'estimation.updated' => 'purple',
            self::TYPE_SPACE_CREATED => 'indigo',
            self::TYPE_LIST_CREATED => 'teal',
            self::TYPE_STATUS_CHANGED => 'cyan',
            self::TYPE_SUBTASK_CREATED => 'blue-grey',
            default => 'grey',
        };
    }
}
