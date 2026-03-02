<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'properties',
        'changes',
    ];

    protected $casts = [
        'properties' => 'array',
        'changes' => 'array',
    ];


    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }


    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->id);
    }


    public static function log(
        Workspace $workspace,
        User $user,
        Model $subject,
        string $action,
        array $properties = [],
        array $changes = []
    ): self {
        return static::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'action' => $action,
            'properties' => $properties,
            'changes' => $changes,
        ]);
    }

    public function getDescriptionAttribute(): string
    {
        $userName = $this->user->name ?? 'Someone';
        $subjectName = $this->properties['name'] ?? 'item';

        return match ($this->action) {
            'created' => "{$userName} created {$subjectName}",
            'updated' => "{$userName} updated {$subjectName}",
            'deleted' => "{$userName} deleted {$subjectName}",
            'completed' => "{$userName} completed {$subjectName}",
            'reopened' => "{$userName} reopened {$subjectName}",
            'assigned' => "{$userName} assigned {$subjectName}",
            'unassigned' => "{$userName} unassigned from {$subjectName}",
            'moved' => "{$userName} moved {$subjectName}",
            'commented' => "{$userName} commented on {$subjectName}",
            'time_logged' => "{$userName} logged time on {$subjectName}",
            default => "{$userName} performed {$this->action} on {$subjectName}",
        };
    }
}
