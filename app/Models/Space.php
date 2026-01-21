<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Space Model 
 *
 * Represents a Space within a Workspace.
 * Spaces contain Folders and Lists for organizing work.
 *
 * Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $workspace_id
 * @property int $created_by
 * @property string $color
 * @property string $avatar
 * @property bool $is_private
 * @property bool $is_starred
 * @property bool $is_active
 * @property int $position
 * @property array|null $features
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Space extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'workspace_id',
        'created_by',
        'color',
        'avatar',
        'is_private',
        'is_starred',
        'is_active',
        'position',
        'features',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_starred' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
        'features' => 'array',
    ];

    protected $attributes = [
        'color' => '#6366F1',
        'is_private' => false,
        'is_starred' => false,
        'is_active' => true,
        'position' => 0,
    ];

    /**
     * Get the workspace that owns the space.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created the space.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all members of the space.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all folders in the space.
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class)->orderBy('position');
    }

    /**
     * Get all lists directly in the space (without folder).
     */
    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class)->whereNull('folder_id')->orderBy('position');
    }

    /**
     * Get all lists in the space (including those in folders).
     */
    public function allLists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    /**
     * Get all labels in the space.
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * Get all custom fields in the space.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * Get all views in the space.
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    /**
     * Get all automations in the space.
     */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    /**
     * Get tasks through lists.
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, TaskList::class, 'space_id', 'list_id');
    }

    /**
     * Scope active spaces.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope starred spaces.
     */
    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }
}
