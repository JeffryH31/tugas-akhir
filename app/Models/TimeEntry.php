<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'subtask_id',
        'user_id',
        'duration',
        'description',
        'started_at',
        'ended_at',
        'is_billable',
        'is_running',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_billable' => 'boolean',
        'is_running' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($entry) {
            if ($entry->subtask) {
                $entry->subtask->update([
                    'time_spent' => $entry->subtask->timeEntries()->sum('duration')
                ]);
            }
        });

        static::updated(function ($entry) {
            if ($entry->subtask) {
                $entry->subtask->update([
                    'time_spent' => $entry->subtask->timeEntries()->sum('duration')
                ]);
            }
        });

        static::deleted(function ($entry) {
            if ($entry->subtask) {
                $entry->subtask->update([
                    'time_spent' => $entry->subtask->timeEntries()->sum('duration')
                ]);
            }
        });
    }


    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function getDurationFormattedAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }


    public function scopeRunning($query)
    {
        return $query->where('is_running', true);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('started_at', $date);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }


    public function stop(): void
    {
        if (!$this->is_running) return;

        $this->update([
            'is_running' => false,
            'ended_at' => now(),
            'duration' => max(1, (int) round($this->started_at->diffInMinutes(now()))),
        ]);
    }

    public static function startTimer(Subtask $subtask, User $user, ?string $description = null): self
    {
        static::where('user_id', $user->id)
            ->where('is_running', true)
            ->each(fn($entry) => $entry->stop());

        return static::create([
            'subtask_id' => $subtask->id,
            'user_id' => $user->id,
            'description' => $description,
            'started_at' => now(),
            'duration' => 0,
            'is_running' => true,
        ]);
    }
}
