<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubtaskResource extends JsonResource
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
            'subtask_id' => $this->subtask_id,
            'task_id' => $this->task_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => new StatusResource($this->whenLoaded('status')),
            'priority' => new PriorityResource($this->whenLoaded('priority')),
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'start_date' => $this->start_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'time_estimate' => $this->time_estimate,
            'time_spent' => $this->time_spent,
            'position' => $this->position,
            'is_archived' => $this->is_archived,
            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->isCompleted(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
