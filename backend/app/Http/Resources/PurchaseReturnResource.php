<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currencyCode = $this->relationLoaded('purchaseOrder') ? $this->purchaseOrder->currency_code : 'BDT';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'return_number' => $this->return_number,
            'status' => $this->status,
            'reason' => $this->reason,
            'credit_amount' => $this->credit_amount === null ? null : (new Money($this->credit_amount, $currencyCode))->toDecimal(),
            'credited_at' => $this->credited_at,
            'note' => $this->note,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status,
                'supplier_name' => $this->purchaseOrder->supplier?->name,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_name' => $item->purchaseOrderItem->product->name,
                'sku' => $item->purchaseOrderItem->product->sku,
                'product_variant_sku' => $item->purchaseOrderItem->productVariant?->sku,
                'quantity' => $item->quantity,
                'unit_cost' => (new Money($item->purchaseOrderItem->unit_cost_amount, $currencyCode))->toDecimal(),
                'line_total' => (new Money($item->quantity * $item->purchaseOrderItem->unit_cost_amount, $currencyCode))->toDecimal(),
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
