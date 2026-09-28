<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currencyCode = $this->product?->currency_code ?? 'BDT';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            // Null means "falls back to the parent product's own price" —
            // returned as null rather than resolved here, so the frontend
            // can show the override state honestly (e.g. "using product
            // price") instead of a value that looks like a real override.
            'price' => $this->price_amount !== null ? (new Money($this->price_amount, $currencyCode))->toDecimal() : null,
            'sale_price' => $this->sale_price_amount !== null ? (new Money($this->sale_price_amount, $currencyCode))->toDecimal() : null,
            'cost_price' => $this->cost_price_amount !== null ? (new Money($this->cost_price_amount, $currencyCode))->toDecimal() : null,
            'status' => $this->status,
            'attribute_values' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->map(fn ($value) => [
                'attribute_id' => $value->product_attribute_id,
                'attribute_name' => $value->attribute->name,
                'value_id' => $value->id,
                'value' => $value->value,
            ])),
            // Total stock across every warehouse this variant has ever been
            // stocked, adjusted, or sold at — the global Stock Levels list
            // stays product-centric, so this is the only place variant-level
            // stock is visible.
            'stock_summary' => $this->whenLoaded('stockLevels', fn () => [
                'total_quantity' => $this->stockLevels->sum('quantity'),
                'total_reserved' => $this->stockLevels->sum('quantity_reserved'),
                'total_available' => $this->stockLevels->sum('quantity') - $this->stockLevels->sum('quantity_reserved'),
                'by_warehouse' => $this->stockLevels->map(fn ($level) => [
                    'warehouse_id' => $level->warehouse_id,
                    'warehouse_name' => $level->warehouse->name,
                    'quantity' => $level->quantity,
                    'quantity_reserved' => $level->quantity_reserved,
                    'quantity_available' => $level->quantity - $level->quantity_reserved,
                ]),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
