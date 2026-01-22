<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TaskList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'folder_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_archived',
        'position',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'settings' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($list) {
            if (empty($list->slug)) {
                $list->slug = Str::slug($list->name);
            }

            // Set position
            if (empty($list->position)) {
                $query = static::where('space_id', $list->space_id);
                if ($list->folder_id) {
                    $query->where('folder_id', $list->folder_id);
                } else {
                    $query->whereNull('folder_id');
                }
                $list->position = $query->max('position') + 1;
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id')->orderBy('position');
    }

    public function allTasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    // ==================== ACCESSORS ====================

    public function getTaskCountAttribute(): int
    {
        return $this->allTasks()->count();
    }

    public function getCompletedTaskCountAttribute(): int
    {
        return $this->allTasks()->whereNotNull('completed_at')->count();
    }

    public function getProgressAttribute(): float
    {
        $total = $this->task_count;
        if ($total === 0) return 0;
        
        return round(($this->completed_task_count / $total) * 100, 1);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    // ==================== HELPER METHODS ====================

    public function archive(): void
    {
        $this->update(['is_archived' => true]);
    }

    public function unarchive(): void
    {
        $this->update(['is_archived' => false]);
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        
        if ($this->folder) {
            $breadcrumbs = $this->folder->getBreadcrumbs();
        }
        
        $breadcrumbs[] = $this;
        
        return $breadcrumbs;
    }
}
