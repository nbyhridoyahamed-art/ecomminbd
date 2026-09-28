<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->component_product_id,
            'product_name' => $this->componentProduct->name,
            'product_sku' => $this->componentProduct->sku,
            'product_variant_id' => $this->component_variant_id,
            'product_variant_sku' => $this->componentVariant?->sku,
            'quantity' => $this->quantity,
        ];
    }
}
