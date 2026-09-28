<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ],
            'product_variant' => $this->product_variant_id ? [
                'id' => $this->productVariant->id,
                'sku' => $this->productVariant->sku,
                'attribute_values' => $this->productVariant->attributeValues->map(fn ($value) => [
                    'attribute_name' => $value->attribute->name,
                    'value' => $value->value,
                ]),
            ] : null,
            'warehouse' => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ],
            'type' => $this->type,
            'quantity' => $this->quantity,
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'reason' => $this->reason,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
