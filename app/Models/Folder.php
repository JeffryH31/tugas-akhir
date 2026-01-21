<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Folder Model 
 *
 * Represents a Folder within a Space.
 * Folders are optional and contain Lists for organizing work.
 *
 * Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 *
 * @property int $id
 * @property string $name
 * @property int $space_id
 * @property int $position
 * @property string|null $color
 * @property bool $hidden
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Folder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'space_id',
        'position',
        'color',
        'hidden',
        'is_active',
    ];

    protected $casts = [
        'position' => 'integer',
        'hidden' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'position' => 0,
        'hidden' => false,
        'is_active' => true,
    ];

    /**
     * Get the space that owns the folder.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the workspace through space.
     */
    public function workspace()
    {
        return $this->space->workspace;
    }

    /**
     * Get all lists in the folder.
     */
    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    /**
     * Get all active lists in the folder.
     */
    public function activeLists(): HasMany
    {
        return $this->lists()->where('is_active', true);
    }

    /**
     * Get all tasks through lists.
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, TaskList::class, 'folder_id', 'list_id');
    }

    /**
     * Get all views for this folder.
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    /**
     * Get all automations for this folder.
     */
    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    /**
     * Scope active folders.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope visible folders (not hidden).
     */
    public function scopeVisible($query)
    {
        return $query->where('hidden', false);
    }
}
