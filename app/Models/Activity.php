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

    protected $appends = [
        'description',
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
        $subjectName = $this->properties['name'] ?? 'item';

        return match ($this->action) {
            'created'              => "created",
            'updated'              => $this->buildUpdateDescription(),
            'deleted'              => "deleted",
            'completed'            => "completed {$subjectName}",
            'reopened'             => "reopened {$subjectName}",
            'assigned'             => $this->buildAssignDescription(),
            'unassigned'           => $this->buildUnassignDescription(),
            'moved'                => $this->buildMovedDescription(),
            'commented'            => $this->buildCommentDescription(),
            'comment_deleted'      => "deleted a comment",
            'comment_resolved'     => "resolved a comment",
            'time_logged'          => $this->buildTimeLoggedDescription(),
            'status_changed'       => $this->buildStatusChangedDescription(),
            'priority_changed'     => $this->buildPriorityChangedDescription(),
            'member_added'         => "added a member",
            'member_removed'       => "removed a member",
            'member_role_updated'  => "updated a member role",
            'dependency_added'     => "added a dependency",
            'dependency_removed'   => "removed a dependency",
            'archived'             => "archived",
            'unarchived'           => "unarchived",
            'label_added'          => $this->buildLabelDescription('added'),
            'label_removed'        => $this->buildLabelDescription('removed'),
            'timer_started'        => "started timer",
            'timer_stopped'        => $this->buildTimerStoppedDescription(),
            'time_updated'         => $this->buildTimeUpdatedDescription(),
            'time_deleted'         => $this->buildTimeDeletedDescription(),
            'deleted_subtask'      => "deleted subtask {$subjectName}",
            'duplicated'           => "duplicated",
            default                => "performed {$this->action}",
        };
    }

    private function buildUpdateDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        if (empty($changes)) {
            return "updated";
        }

        $changeList = [];
        foreach (array_slice($changes, 0, 2, true) as $field => $change) {
            $rawOld = $change['old'] ?? null;
            $rawNew = $change['new'] ?? null;
            // Skip array-value fields (e.g. raw assignee IDs) – they are logged separately
            if (is_array($rawOld) || is_array($rawNew)) {
                continue;
            }
            $oldVal     = $rawOld ?? '—';
            $newVal     = $rawNew ?? '—';
            $fieldLabel = str_replace('_', ' ', $field);
            $changeList[] = "{$fieldLabel}: {$oldVal} -> {$newVal}";
        }

        if (empty($changeList)) {
            return "updated";
        }

        $summary = implode(', ', $changeList);
        if (count($changes) > 2) {
            $summary .= ', ...';
        }

        return "updated ({$summary})";
    }

    private function buildAssignDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];

        if (!empty($this->properties['assignee_name'])) {
            return "assigned {$this->properties['assignee_name']}";
        }

        $assignees = $changes['assignees']['new'] ?? [];
        if (is_array($assignees) && !empty($assignees)) {
            return 'assigned ' . implode(', ', $assignees);
        }

        return "assigned";
    }

    private function buildUnassignDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];

        if (!empty($this->properties['assignee_name'])) {
            return "unassigned {$this->properties['assignee_name']}";
        }

        $assignees = $changes['assignees']['old'] ?? [];
        if (is_array($assignees) && !empty($assignees)) {
            return 'unassigned ' . implode(', ', $assignees);
        }

        return "unassigned";
    }

    private function buildLabelDescription(string $action): string
    {
        $labelName = $this->properties['label_name'] ?? 'a label';
        return "{$action} label '{$labelName}'";
    }

    private function buildStatusChangedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['status']['old'] ?? null;
        $new = $changes['status']['new'] ?? null;

        if ($old || $new) {
            return "changed status from " . ($old ?? 'none') . " to " . ($new ?? 'none');
        }

        return "changed status";
    }

    private function buildPriorityChangedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['priority']['old'] ?? null;
        $new = $changes['priority']['new'] ?? null;

        if ($new !== null && $old !== null) {
            return "changed priority to {$new} (from {$old})";
        }

        if ($new !== null) {
            return "set priority to {$new}";
        }

        if ($old !== null) {
            return "cleared priority (was {$old})";
        }

        return "changed priority";
    }

    private function buildMovedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['list']['old'] ?? null;
        $new = $changes['list']['new'] ?? null;

        if ($old || $new) {
            return "moved from " . ($old ?? 'unknown list') . " to " . ($new ?? 'unknown list');
        }

        return "moved";
    }

    private function buildCommentDescription(): string
    {
        $preview = $this->properties['comment_preview'] ?? null;

        if ($preview) {
            return "commented: \"{$preview}\"";
        }

        return "commented";
    }

    private function buildTimeLoggedDescription(): string
    {
        $duration = $this->properties['duration_formatted'] ?? null;

        if ($duration) {
            return "logged {$duration}";
        }

        return "logged time";
    }

    private function buildTimerStoppedDescription(): string
    {
        $duration = $this->properties['duration_formatted'] ?? null;

        if ($duration) {
            return "stopped timer after {$duration}";
        }

        return "stopped timer";
    }

    private function buildTimeUpdatedDescription(): string
    {
        $changes = $this->getAttribute('changes') ?? [];
        $old = $changes['duration']['old'] ?? null;
        $new = $changes['duration']['new'] ?? null;

        if ($old !== null || $new !== null) {
            return "updated time entry (" . ($old ?? '0') . "m -> " . ($new ?? '0') . "m)";
        }

        return "updated time entry";
    }

    private function buildTimeDeletedDescription(): string
    {
        $duration = $this->properties['duration'] ?? null;

        if ($duration !== null) {
            return "deleted {$duration}m time entry";
        }

        return "deleted time entry";
    }
}
