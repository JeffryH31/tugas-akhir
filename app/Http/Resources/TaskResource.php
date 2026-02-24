<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'task_id' => $this->task_id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'progress' => $this->progress,
            'status_id' => $this->status_id,
            'priority_id' => $this->priority_id,
            'due_date' => $this->due_date,
            'start_date' => $this->start_date,
            'completed_at' => $this->completed_at,
            'time_estimate' => $this->time_estimate,
            'time_spent' => $this->time_spent,
            'sprint_id' => $this->sprint_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'status' => new StatusResource($this->whenLoaded('status')),
            'priority' => new PriorityResource($this->whenLoaded('priority')),
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'watchers' => UserResource::collection($this->whenLoaded('watchers')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'dependencies' => TaskResource::collection($this->whenLoaded('dependencies')),
            'dependents' => TaskResource::collection($this->whenLoaded('dependents')),
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'time_entries' => TimeEntryResource::collection($this->whenLoaded('timeEntries')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            
            // Counts
            'comments_count' => $this->comments_count ?? $this->comments?->count() ?? 0,
            'subtasks_count' => $this->subtasks_count ?? $this->subtasks?->count() ?? 0,
            'attachments_count' => $this->attachments_count ?? $this->attachments?->count() ?? 0,
        ];
    }
}
