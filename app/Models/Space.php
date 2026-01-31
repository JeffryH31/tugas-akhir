<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Space extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_private',
        'is_starred',
        'position',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_starred' => 'boolean',
        'settings' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($space) {
            if (empty($space->slug)) {
                $space->slug = Str::slug($space->name);
            }
            
            // Ensure unique slug within workspace
            $originalSlug = $space->slug;
            $count = 1;
            while (static::where('workspace_id', $space->workspace_id)
                ->where('slug', $space->slug)->exists()) {
                $space->slug = $originalSlug . '-' . $count++;
            }

            // Set position
            if (empty($space->position)) {
                $space->position = static::where('workspace_id', $space->workspace_id)->max('position') + 1;
            }
        });

        static::created(function ($space) {
            // Create default statuses
            $defaultStatuses = [
                ['name' => 'Open', 'color' => '#6B7280', 'type' => 'open', 'position' => 0, 'is_default' => true],
                ['name' => 'In Progress', 'color' => '#3B82F6', 'type' => 'in_progress', 'position' => 1],
                ['name' => 'Review', 'color' => '#F59E0B', 'type' => 'review', 'position' => 2],
                ['name' => 'Completed', 'color' => '#10B981', 'type' => 'closed', 'position' => 3, 'is_closed' => true],
            ];

            foreach ($defaultStatuses as $status) {
                $space->statuses()->create([
                    ...$status,
                    'slug' => Str::slug($status['name']),
                ]);
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class)->whereNull('parent_id')->orderBy('position');
    }

    public function allFolders(): HasMany
    {
        return $this->hasMany(Folder::class)->orderBy('position');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(TaskList::class)->orderBy('position');
    }

    public function listsWithoutFolder(): HasMany
    {
        return $this->hasMany(TaskList::class)->whereNull('folder_id')->orderBy('position');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Task::class, TaskList::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderBy('position');
    }

    public function activeSprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->where('is_active', true)->orderBy('position');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    // ==================== ACCESSORS ====================

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->name, 0, 1));
    }

    // ==================== SCOPES ====================

    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    // ==================== HELPER METHODS ====================

    public function toggleStar(): void
    {
        $this->update(['is_starred' => !$this->is_starred]);
    }

    public function getDefaultStatus(): ?Status
    {
        return $this->statuses()->where('is_default', true)->first()
            ?? $this->statuses()->first();
    }
}
