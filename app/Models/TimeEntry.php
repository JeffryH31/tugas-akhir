<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TimeEntry Model
 *
 * Represents a time log entry for a task.
 * Supports both timer-based automatic logging and manual entry.
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property float $hours
 * @property \Illuminate\Support\Carbon $work_date
 * @property string|null $description
 * @property bool $is_timer_entry
 * @property \Illuminate\Support\Carbon|null $timer_started_at
 * @property \Illuminate\Support\Carbon|null $timer_stopped_at
 * @property bool $is_billable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read Task $task
 * @property-read User $user
 */
class TimeEntry extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'hours',
        'duration_minutes',
        'work_date',
        'logged_date',
        'description',
        'is_timer_entry',
        'timer_started_at',
        'timer_stopped_at',
        'started_at',
        'is_running',
        'is_billable',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'hours' => 'decimal:2',
        'duration_minutes' => 'integer',
        'work_date' => 'date',
        'logged_date' => 'date',
        'is_timer_entry' => 'boolean',
        'timer_started_at' => 'datetime',
        'timer_stopped_at' => 'datetime',
        'started_at' => 'datetime',
        'is_running' => 'boolean',
        'is_billable' => 'boolean',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hours' => 0,
        'duration_minutes' => 0,
        'is_timer_entry' => false,
        'is_running' => false,
        'is_billable' => true,
    ];

    /**
     * Get the task this time entry belongs to.
     *
     * @return BelongsTo<Task, TimeEntry>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user who logged this time.
     *
     * @return BelongsTo<User, TimeEntry>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to get entries by user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<TimeEntry> $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder<TimeEntry>
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to get entries within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder<TimeEntry> $query
     * @param \Illuminate\Support\Carbon $startDate
     * @param \Illuminate\Support\Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder<TimeEntry>
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('work_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get billable entries only.
     *
     * @param \Illuminate\Database\Eloquent\Builder<TimeEntry> $query
     * @return \Illuminate\Database\Eloquent\Builder<TimeEntry>
     */
    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    /**
     * Scope a query to get timer-logged entries.
     *
     * @param \Illuminate\Database\Eloquent\Builder<TimeEntry> $query
     * @return \Illuminate\Database\Eloquent\Builder<TimeEntry>
     */
    public function scopeTimerLogged($query)
    {
        return $query->where('is_timer_entry', true);
    }

    /**
     * Get the duration in a human-readable format.
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
        $hours = floor($this->hours);
        $minutes = round(($this->hours - $hours) * 60);

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Create a time entry from timer data.
     *
     * @param Task $task
     * @param User $user
     * @param \Illuminate\Support\Carbon $startTime
     * @param \Illuminate\Support\Carbon $endTime
     * @param string|null $description
     * @return static
     */
    public static function createFromTimer(
        Task $task,
        User $user,
        $startTime,
        $endTime,
        ?string $description = null
    ): static {
        $hours = $startTime->diffInMinutes($endTime) / 60;

        return static::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'hours' => round($hours, 2),
            'work_date' => $startTime->toDateString(),
            'description' => $description ?? 'Timer session',
            'is_timer_entry' => true,
            'timer_started_at' => $startTime,
            'timer_stopped_at' => $endTime,
        ]);
    }
}
