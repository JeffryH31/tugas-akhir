<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template Model 
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $workspace_id
 * @property int $created_by
 * @property string $type
 * @property array $template_data
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'workspace_id',
        'created_by',
        'type',
        'template_data',
        'is_public',
    ];

    protected $casts = [
        'template_data' => 'array',
        'is_public' => 'boolean',
    ];

    protected $attributes = [
        'type' => 'task',
        'is_public' => false,
    ];

    /**
     * Template types.
     */
    public const TYPE_TASK = 'task';
    public const TYPE_LIST = 'list';
    public const TYPE_FOLDER = 'folder';
    public const TYPE_SPACE = 'space';

    /**
     * Get the workspace that owns the template.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the creator of the template.
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
     * Scope public templates.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Apply template to create a new entity.
     */
    public function apply(array $overrides = []): Model
    {
        $data = array_merge($this->template_data, $overrides);

        return match ($this->type) {
            self::TYPE_TASK => Task::create($data),
            self::TYPE_LIST => TaskList::create($data),
            self::TYPE_FOLDER => Folder::create($data),
            self::TYPE_SPACE => Space::create($data),
            default => throw new \InvalidArgumentException("Unknown template type: {$this->type}"),
        };
    }
}
