<?php

namespace App\Http\Resources;

use App\Models\Folder;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_url' => $this->resolveSubjectUrl(),
            'context' => $this->resolveContext(),
            'properties' => $this->properties ?? [],
            'changes' => $this->changes ?? [],
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    private function resolveSubjectUrl(): ?string
    {
        $subject = $this->relationLoaded('subject') ? $this->subject : null;
        if (! $subject) {
            return null;
        }

        $workspaceId = $this->workspace_id;

        return match (true) {
            $subject instanceof Workspace =>
                route('workspaces.show', $workspaceId),

            $subject instanceof Space =>
                route('spaces.show', [$workspaceId, $subject->id]),

            $subject instanceof Folder =>
                $subject->space_id
                    ? route('spaces.show', [$workspaceId, $subject->space_id])
                    : null,

            $subject instanceof TaskList =>
                $subject->space_id
                    ? route('lists.show', [$workspaceId, $subject->space_id, $subject->id])
                    : null,

            $subject instanceof Task =>
                $subject->taskList?->space_id
                    ? route('lists.show', [$workspaceId, $subject->taskList->space_id, $subject->task_list_id])
                    : null,

            $subject instanceof Subtask =>
                $subject->task?->taskList?->space_id
                    ? route('lists.show', [$workspaceId, $subject->task->taskList->space_id, $subject->task->task_list_id])
                    : null,

            default => null,
        };
    }

    private function resolveContext(): ?string
    {
        $subject = $this->relationLoaded('subject') ? $this->subject : null;
        if (! $subject) {
            return null;
        }

        return match (true) {
            $subject instanceof Task =>
                $subject->taskList?->space
                    ? $subject->taskList->space->name . ' / ' . $subject->taskList->name
                    : null,

            $subject instanceof Subtask =>
                $subject->task?->taskList?->space
                    ? $subject->task->taskList->space->name . ' / ' . $subject->task->taskList->name
                    : null,

            $subject instanceof TaskList =>
                $subject->space?->name,

            $subject instanceof Folder =>
                $subject->space?->name,

            default => null,
        };
    }
}
