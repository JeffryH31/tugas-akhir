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
 * Workspace Model 
 *
 * Represents a workspace/organization - the top level of Hierarchy.
 * 
 * Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $owner_id
 * @property string $color
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Space> $spaces
 */
class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'color',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all members of the workspace.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all spaces in the workspace.
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class)->orderBy('position');
    }

    /**
     * Get only active spaces in the workspace.
     */
    public function activeSpaces(): HasMany
    {
        return $this->spaces()->where('is_active', true);
    }

    /**
     * Get all goals in the workspace.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Get all templates in the workspace.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * Get all folders through spaces.
     */
    public function folders(): HasManyThrough
    {
        return $this->hasManyThrough(Folder::class, Space::class);
    }

    /**
     * Get all lists through spaces.
     */
    public function lists(): HasManyThrough
    {
        return $this->hasManyThrough(TaskList::class, Space::class);
    }

    // ==========================================
    // Legacy aliases for backward compatibility
    // ==========================================
    
    /**
     * @deprecated Use spaces() instead
     */
    public function boards(): HasMany
    {
        return $this->spaces();
    }

    /**
     * @deprecated Use activeSpaces() instead
     */
    public function activeBoards(): HasMany
    {
        return $this->activeSpaces();
    }

    /**
     * Scope a query to only include active workspaces.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Workspace> $query
     * @return \Illuminate\Database\Eloquent\Builder<Workspace>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include workspaces owned by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Workspace> $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder<Workspace>
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('owner_id', $userId);
    }

    /**
     * Scope a query to include workspaces accessible by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Workspace> $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder<Workspace>
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where('owner_id', $userId)
            ->orWhereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
    }

    /**
     * Check if a user is a member of the workspace.
     *
     * @param int $userId
     * @return bool
     */
    public function hasMember(int $userId): bool
    {
        return $this->owner_id === $userId
            || $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if a user is an admin of the workspace.
     *
     * @param int $userId
     * @return bool
     */
    public function isAdmin(int $userId): bool
    {
        return $this->owner_id === $userId
            || $this->members()
                ->where('user_id', $userId)
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
    }
}
