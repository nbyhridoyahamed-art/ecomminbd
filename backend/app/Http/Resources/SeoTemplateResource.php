<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeoTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'entity_type' => $this->entity_type,
            'title_template' => $this->title_template,
            'description_template' => $this->description_template,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
