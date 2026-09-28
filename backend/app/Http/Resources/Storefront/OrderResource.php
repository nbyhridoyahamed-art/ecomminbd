<?php

namespace App\Http\Resources\Storefront;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public receipt shape for a storefront order — looked up by uuid only
 * (see CheckoutController::show()), never the sequential id. Deliberately
 * leaves out everything fulfillment-internal that the admin OrderResource
 * exposes: warehouse, staff attribution, status history with staff notes.
 * A guest only sees what they'd expect on an order confirmation page.
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
                'product_name' => $item->product->name,
                'product_slug' => $item->product->slug,
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
            'subtotal_amount' => (new Money($subtotalMinor, $this->currency_code))->toDecimal(),
            'total_amount' => (new Money(
                $subtotalMinor + $this->shipping_amount - $this->discount_amount,
                $this->currency_code,
            ))->toDecimal(),
            'created_at' => $this->created_at,
        ];
    }
}
