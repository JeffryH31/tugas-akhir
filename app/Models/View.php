<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * View Model 
 *
 * Supports: List, Board, Calendar, Gantt, Timeline, Table, Mindmap, Workload
 *
 * @property int $id
 * @property string $name
 * @property int|null $space_id
 * @property int|null $folder_id
 * @property int|null $list_id
 * @property int $created_by
 * @property string $type
 * @property array|null $filters
 * @property array|null $sorts
 * @property array|null $grouping
 * @property array|null $columns
 * @property bool $is_private
 * @property bool $is_pinned
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class View extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'space_id',
        'folder_id',
        'list_id',
        'created_by',
        'type',
        'filters',
        'sorts',
        'grouping',
        'columns',
        'is_private',
        'is_pinned',
        'position',
    ];

    protected $casts = [
        'filters' => 'array',
        'sorts' => 'array',
        'grouping' => 'array',
        'columns' => 'array',
        'is_private' => 'boolean',
        'is_pinned' => 'boolean',
        'position' => 'integer',
    ];

    protected $attributes = [
        'type' => 'list',
        'is_private' => false,
        'is_pinned' => false,
        'position' => 0,
    ];

    /**
     * View types.
     */
    public const TYPE_LIST = 'list';
    public const TYPE_BOARD = 'board';
    public const TYPE_CALENDAR = 'calendar';
    public const TYPE_GANTT = 'gantt';
    public const TYPE_TIMELINE = 'timeline';
    public const TYPE_TABLE = 'table';
    public const TYPE_MINDMAP = 'mindmap';
    public const TYPE_WORKLOAD = 'workload';

    public const TYPES = [
        self::TYPE_LIST,
        self::TYPE_BOARD,
        self::TYPE_CALENDAR,
        self::TYPE_GANTT,
        self::TYPE_TIMELINE,
        self::TYPE_TABLE,
        self::TYPE_MINDMAP,
        self::TYPE_WORKLOAD,
    ];

    /**
     * Get the space that owns the view.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the folder that owns the view.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the list that owns the view.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'list_id');
    }

    /**
     * Get the creator of the view.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope public views.
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope pinned views.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}
