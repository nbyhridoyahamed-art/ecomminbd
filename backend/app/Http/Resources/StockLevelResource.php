<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Product loaded for one warehouse (see StockLevelController::index)
 * with its computed on-hand/reserved quantity at that warehouse — the
 * "warehouse_quantity"/"warehouse_reserved" attributes come from the
 * query's raw select, not a relation.
 */
class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quantity = (int) $this->warehouse_quantity;
        $reserved = (int) $this->warehouse_reserved;
        $available = $quantity - $reserved;
        $isLowStock = $this->track_stock
            && $this->low_stock_threshold !== null
            && $available <= $this->low_stock_threshold;

        return [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $quantity,
            'quantity_reserved' => $reserved,
            'quantity_available' => $available,
            'track_stock' => (bool) $this->track_stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_low_stock' => $isLowStock,
        ];
    }
}
