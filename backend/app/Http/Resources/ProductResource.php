<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null),
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,

            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,

            'description' => $this->description,
            'short_description' => $this->short_description,

            'currency_code' => $this->currency_code,
            'price' => (new Money($this->price_amount, $this->currency_code))->toDecimal(),
            'sale_price' => $this->sale_price_amount !== null
                ? (new Money($this->sale_price_amount, $this->currency_code))->toDecimal()
                : null,
            'cost_price' => $this->cost_price_amount !== null
                ? (new Money($this->cost_price_amount, $this->currency_code))->toDecimal()
                : null,
            'compare_at_price' => $this->compare_at_price_amount !== null
                ? (new Money($this->compare_at_price_amount, $this->currency_code))->toDecimal()
                : null,

            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,

            'track_stock' => $this->track_stock,
            'low_stock_threshold' => $this->low_stock_threshold,

            'status' => $this->status,
            'featured' => $this->featured,

            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'focus_keyword' => $this->focus_keyword,

            'images' => ProductImageResource::collection($this->whenLoaded('images')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
