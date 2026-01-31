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
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'time_entries' => TimeEntryResource::collection($this->whenLoaded('timeEntries')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
