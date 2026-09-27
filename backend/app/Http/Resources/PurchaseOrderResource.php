<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'currency_code' => $this->currency_code,
            'notes' => $this->notes,
            'warehouse' => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name],
            'supplier' => ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'sku' => $item->product->sku,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'quantity_remaining' => $item->quantityRemaining(),
                'unit_cost' => (new Money($item->unit_cost_amount, $this->currency_code))->toDecimal(),
            ])),
            'total_amount' => $this->when(
                $this->relationLoaded('items'),
                fn () => (new Money(
                    $this->items->sum(fn ($item) => $item->quantity_ordered * $item->unit_cost_amount),
                    $this->currency_code,
                ))->toDecimal(),
            ),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn ($receipt) => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'note' => $receipt->note,
                'received_by' => $receipt->receiver?->name,
                'created_at' => $receipt->created_at,
                'items' => $receipt->items->map(fn ($receiptItem) => [
                    'product_name' => $receiptItem->orderItem->product->name,
                    'quantity_received' => $receiptItem->quantity_received,
                ]),
            ])),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
