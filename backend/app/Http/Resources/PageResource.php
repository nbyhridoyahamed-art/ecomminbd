<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
