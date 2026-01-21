<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model 
 *
 * Represents a user in the project management system.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
        'initials',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ==========================================
    // Workspace & Space Relationships
    // ==========================================

    /**
     * Get workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get workspaces the user is a member of.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all workspaces accessible by the user.
     */
    public function accessibleWorkspaces()
    {
        return Workspace::active()
            ->accessibleBy($this->id)
            ->get();
    }

    /**
     * Get spaces the user is a member of.
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'space_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get starred spaces for the user.
     */
    public function starredSpaces(): BelongsToMany
    {
        return $this->spaces()->where('is_starred', true);
    }

    // ==========================================
    // List Relationships
    // ==========================================

    /**
     * Get lists assigned to the user.
     */
    public function assignedLists(): BelongsToMany
    {
        return $this->belongsToMany(TaskList::class, 'list_user', 'user_id', 'list_id')
            ->withTimestamps();
    }

    // ==========================================
    // Task Relationships
    // ==========================================

    /**
     * Get tasks where user is one of the assignees.
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withTimestamps();
    }

    /**
     * Get tasks where user is one of the assignees (alias).
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withTimestamps();
    }

    /**
     * Get tasks created by the user.
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Get tasks the user is watching.
     */
    public function watchingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_watchers')
            ->withTimestamps();
    }

    // ==========================================
    // Time Tracking
    // ==========================================

    /**
     * Get all time entries logged by the user.
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Get total hours logged by the user.
     */
    public function getTotalHoursLogged($startDate = null, $endDate = null): float
    {
        $query = $this->timeEntries();

        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        return (float) $query->sum('hours');
    }

    // ==========================================
    // Activities & Comments
    // ==========================================

    /**
     * Get all activities performed by the user.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get all comments by the user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // ==========================================
    // Goals
    // ==========================================

    /**
     * Get goals owned by the user.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'owner_id');
    }

    // ==========================================
    // Attributes
    // ==========================================

    /**
     * Get the user's initials for avatar display.
     */
    public function getInitialsAttribute(): string
    {
        $names = explode(' ', trim($this->name));
        $initials = '';

        foreach (array_slice($names, 0, 2) as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }

        return $initials ?: 'U';
    }

    /**
     * Get a consistent color for the user based on their ID.
     */
    public function getAvatarColorAttribute(): string
    {
        $colors = [
            '#6366F1', '#EC4899', '#10B981', '#F59E0B',
            '#0EA5E9', '#8B5CF6', '#EF4444', '#14B8A6',
        ];

        return $colors[$this->id % count($colors)];
    }

    /**
     * Get count of completed tasks by the user.
     */
    public function getCompletedTasksCount($startDate = null, $endDate = null): int
    {
        $query = $this->assignedTasks()->completed();

        if ($startDate && $endDate) {
            $query->whereBetween('completed_at', [$startDate, $endDate]);
        }

        return $query->count();
    }

    // ==========================================
    // Legacy aliases for backward compatibility
    // ==========================================

    /**
     * @deprecated Use spaces() instead
     */
    public function boards(): BelongsToMany
    {
        return $this->spaces();
    }

    /**
     * @deprecated Use starredSpaces() instead
     */
    public function starredBoards(): BelongsToMany
    {
        return $this->starredSpaces();
    }

    /**
     * @deprecated Use assignedLists() instead
     */
    public function assignedFeatures(): BelongsToMany
    {
        return $this->assignedLists();
    }
}
