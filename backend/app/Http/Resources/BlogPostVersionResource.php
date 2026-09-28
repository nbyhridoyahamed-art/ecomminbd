<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'snapshot' => $this->snapshot,
            'created_by' => $this->whenLoaded('editor', fn () => $this->editor?->name),
            'created_at' => $this->created_at,
        ];
    }
}
