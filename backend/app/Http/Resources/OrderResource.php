<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotalMinor = $this->relationLoaded('items')
            ? $this->items->sum(fn ($item) => $item->quantity * $item->unit_price_amount)
            : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'currency_code' => $this->currency_code,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $this->warehouse->id, 'name' => $this->warehouse->name]),
            'shipping' => [
                'customer_address_id' => $this->customer_address_id,
                'recipient_name' => $this->shipping_recipient_name,
                'phone' => $this->shipping_phone,
                'address_line' => $this->shipping_address_line,
                'division' => $this->whenLoaded('shippingDivision', fn () => $this->shippingDivision?->name_en),
                'district' => $this->whenLoaded('shippingDistrict', fn () => $this->shippingDistrict?->name_en),
                'upazila' => $this->whenLoaded('shippingUpazila', fn () => $this->shippingUpazila?->name_en),
            ],
            'shipping_amount' => (new Money($this->shipping_amount, $this->currency_code))->toDecimal(),
            'discount_amount' => (new Money($this->discount_amount, $this->currency_code))->toDecimal(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
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
                'unit_price' => (new Money($item->unit_price_amount, $this->currency_code))->toDecimal(),
                'line_total' => (new Money($item->quantity * $item->unit_price_amount, $this->currency_code))->toDecimal(),
            ])),
            'subtotal_amount' => $subtotalMinor === null ? null : (new Money($subtotalMinor, $this->currency_code))->toDecimal(),
            'total_amount' => $subtotalMinor === null ? null : (new Money(
                $subtotalMinor + $this->shipping_amount - $this->discount_amount,
                $this->currency_code,
            ))->toDecimal(),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'note' => $entry->note,
                'created_by' => $entry->creator?->name,
                'created_at' => $entry->created_at,
            ])),
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'id' => $this->shipment->id,
                'tracking_number' => $this->shipment->tracking_number,
                'status' => $this->shipment->status,
                'courier_name' => $this->shipment->courier?->name,
            ] : null),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($return) => [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'status' => $return->status,
                'refund_amount' => $return->refund_amount === null ? null : (new Money($return->refund_amount, $this->currency_code))->toDecimal(),
            ])),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
