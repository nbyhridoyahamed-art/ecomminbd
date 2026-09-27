<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'purchase_order_id' => $this->purchase_order_id,
            'receipt_number' => $this->receipt_number,
            'note' => $this->note,
            'received_by' => $this->receiver?->name,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_name' => $item->orderItem->product->name,
                'sku' => $item->orderItem->product->sku,
                'quantity_received' => $item->quantity_received,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
