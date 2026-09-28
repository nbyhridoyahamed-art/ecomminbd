<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'type' => $this->type,
            'settings' => $this->settings,
            'styles' => $this->styles ?? (object) [],
            'responsive' => $this->responsive ?? (object) [],
            'visibility' => $this->visibility ?? (object) [],
            'animation' => $this->animation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
