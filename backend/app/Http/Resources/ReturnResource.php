<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currencyCode = $this->relationLoaded('order') ? $this->order->currency_code : 'BDT';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'return_number' => $this->return_number,
            'status' => $this->status,
            'reason' => $this->reason,
            'refund_amount' => $this->refund_amount === null ? null : (new Money($this->refund_amount, $currencyCode))->toDecimal(),
            'refunded_at' => $this->refunded_at,
            'note' => $this->note,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
                'payment_status' => $this->order->payment_status,
                'customer_name' => $this->order->customer?->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'product_name' => $item->orderItem->product->name,
                'sku' => $item->orderItem->product->sku,
                'quantity' => $item->quantity,
                'unit_price' => (new Money($item->orderItem->unit_price_amount, $currencyCode))->toDecimal(),
                'line_total' => (new Money($item->quantity * $item->orderItem->unit_price_amount, $currencyCode))->toDecimal(),
                'restock' => $item->restock,
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'created_by' => $entry->creator?->name,
                'created_at' => $entry->created_at,
            ])),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
