<?php

namespace App\Http\Resources\Storefront;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public-safe slice of a variant — never cost_price, which the admin
 * ProductVariantResource exposes for margin visibility staff need but a
 * customer never should.
 */
class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currencyCode = $this->product?->currency_code ?? 'BDT';
        $price = $this->price_amount ?? $this->product?->price_amount;
        $salePrice = $this->sale_price_amount ?? $this->product?->sale_price_amount;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $price !== null ? (new Money($price, $currencyCode))->toDecimal() : null,
            'sale_price' => $salePrice !== null ? (new Money($salePrice, $currencyCode))->toDecimal() : null,
            'attribute_values' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->map(fn ($value) => [
                'attribute_name' => $value->attribute->name,
                'value' => $value->value,
            ])),
            'in_stock' => (bool) $this->in_stock,
        ];
    }
}
