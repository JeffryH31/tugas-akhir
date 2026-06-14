<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Automation Model
 *
 * @property int $id
 * @property string $name
 * @property int|null $space_id
 * @property int|null $folder_id
 * @property int|null $list_id
 * @property int $created_by
 * @property array $trigger
 * @property array $actions
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Automation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'space_id',
        'folder_id',
        'list_id',
        'created_by',
        'trigger',
        'actions',
        'is_active',
    ];

    protected $casts = [
        'trigger' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Trigger types.
     */
    public const TRIGGER_TASK_CREATED = 'task_created';

    public const TRIGGER_TASK_STATUS_CHANGED = 'task_status_changed';

    public const TRIGGER_TASK_ASSIGNEE_CHANGED = 'task_assignee_changed';

    public const TRIGGER_TASK_DUE_DATE = 'task_due_date';

    public const TRIGGER_TASK_COMPLETED = 'task_completed';

    public const TRIGGER_SUBTASK_CREATED = 'subtask_created';

    public const TRIGGER_COMMENT_ADDED = 'comment_added';

    /**
     * Action types.
     */
    public const ACTION_CHANGE_STATUS = 'change_status';

    public const ACTION_ADD_ASSIGNEE = 'add_assignee';

    public const ACTION_REMOVE_ASSIGNEE = 'remove_assignee';

    public const ACTION_SET_PRIORITY = 'set_priority';

    public const ACTION_ADD_LABEL = 'add_label';

    public const ACTION_SEND_NOTIFICATION = 'send_notification';

    public const ACTION_CREATE_TASK = 'create_task';

    public const ACTION_MOVE_TASK = 'move_task';

    /**
     * Get the space that owns the automation.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the folder that owns the automation.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the list that owns the automation.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    /**
     * Get the creator of the automation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope active automations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
