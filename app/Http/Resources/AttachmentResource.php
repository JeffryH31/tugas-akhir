<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
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
            'name' => $this->name,
            'original_name' => $this->original_name,
            'path' => $this->path,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'mime_type' => $this->mime_type,
            'url' => $this->url,
            'is_image' => $this->is_image,
            'icon' => $this->icon,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
