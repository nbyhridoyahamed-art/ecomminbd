<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'transfer_number' => $this->transfer_number,
            'from_warehouse' => [
                'id' => $this->fromWarehouse->id,
                'name' => $this->fromWarehouse->name,
            ],
            'to_warehouse' => [
                'id' => $this->toWarehouse->id,
                'name' => $this->toWarehouse->name,
            ],
            'status' => $this->status,
            'note' => $this->note,
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'id' => $entry->id,
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'created_by' => $entry->creator?->name,
                'created_at' => $entry->created_at,
            ])),
            'movements' => $this->whenLoaded('movements', fn () => $this->movements->map(fn ($movement) => [
                'id' => $movement->id,
                'warehouse' => ['id' => $movement->warehouse->id, 'name' => $movement->warehouse->name],
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'quantity_before' => $movement->quantity_before,
                'quantity_after' => $movement->quantity_after,
                'created_at' => $movement->created_at,
            ])),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'product_variant' => $item->product_variant_id ? [
                    'id' => $item->productVariant->id,
                    'sku' => $item->productVariant->sku,
                    'attribute_values' => $item->productVariant->attributeValues->map(fn ($value) => [
                        'attribute_name' => $value->attribute->name,
                        'value' => $value->value,
                    ]),
                ] : null,
                'quantity' => $item->quantity,
            ])),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
