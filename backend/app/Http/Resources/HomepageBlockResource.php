<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'type' => $this->type,
            'settings' => $this->settings,
            'styles' => $this->styles ?? (object) [],
            'responsive' => $this->responsive ?? (object) [],
            'visibility' => $this->visibility ?? (object) [],
            'animation' => $this->animation,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'scheduled_at' => $this->scheduled_at,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
