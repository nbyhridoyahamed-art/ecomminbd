<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'warehouse' => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ],
            'note' => $this->note,
            'movements' => $this->whenLoaded('movements', fn () => StockMovementResource::collection($this->movements)),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
