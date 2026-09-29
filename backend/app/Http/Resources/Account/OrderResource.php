<?php

namespace App\Http\Resources\Account;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An authenticated customer's own order — like Storefront\OrderResource
 * (no warehouse, no created_by), but with a status timeline added since
 * that's the whole point of a signed-in customer checking on an order.
 * The timeline itself still drops each entry's staff note/created_by —
 * an internal remark isn't customer-facing just because the customer can
 * now see the entry exists.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotalMinor = $this->items->sum(fn ($item) => $item->quantity * $item->unit_price_amount);

        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'source' => $this->source,
            'currency_code' => $this->currency_code,
            'notes' => $this->notes,
            'shipping' => [
                'recipient_name' => $this->shipping_recipient_name,
                'phone' => $this->shipping_phone,
                'address_line' => $this->shipping_address_line,
                'division' => $this->whenLoaded('shippingDivision', fn () => $this->shippingDivision?->name_en),
                'district' => $this->whenLoaded('shippingDistrict', fn () => $this->shippingDistrict?->name_en),
                'upazila' => $this->whenLoaded('shippingUpazila', fn () => $this->shippingUpazila?->name_en),
            ],
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'product_slug' => $item->product->slug,
                'reviewable' => (bool) ($item->reviewable ?? false),
                'product_variant' => $item->product_variant_id ? [
                    'sku' => $item->productVariant->sku,
                    'attribute_values' => $item->productVariant->attributeValues->map(fn ($value) => [
                        'attribute_name' => $value->attribute->name,
                        'value' => $value->value,
                    ]),
                ] : null,
                'quantity' => $item->quantity,
                'unit_price' => (new Money($item->unit_price_amount, $this->currency_code))->toDecimal(),
                'line_total' => (new Money($item->quantity * $item->unit_price_amount, $this->currency_code))->toDecimal(),
            ]),
            'shipping_amount' => (new Money($this->shipping_amount, $this->currency_code))->toDecimal(),
            'discount_amount' => (new Money($this->discount_amount, $this->currency_code))->toDecimal(),
            'store_credit_amount' => (new Money($this->store_credit_amount, $this->currency_code))->toDecimal(),
            'subtotal_amount' => (new Money($subtotalMinor, $this->currency_code))->toDecimal(),
            'total_amount' => (new Money(
                $subtotalMinor + $this->shipping_amount - $this->discount_amount - $this->store_credit_amount,
                $this->currency_code,
            ))->toDecimal(),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry) => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'created_at' => $entry->created_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
