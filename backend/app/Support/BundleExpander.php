<?php

namespace App\Support;

use App\Models\BundleItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Resolves an order line item (product + optional variant + quantity) into
 * the product/variant/quantity rows that actually carry stock — an identity
 * expansion for a simple/variable product, or one row per component for a
 * bundle (quantity multiplied by how many the bundle itself needs). The
 * single place bundle composition gets read; everywhere else (Order/Return/
 * Shipment controllers) just loops the result without knowing or caring
 * whether the original item was a bundle.
 */
final class BundleExpander
{
    /**
     * @return Collection<int, object{product_id: int, product_variant_id: ?int, quantity: int}>
     */
    public static function expand(int $productId, ?int $productVariantId, int $quantity): Collection
    {
        $product = Product::find($productId);

        if (! $product || $product->type !== 'bundle') {
            return collect([(object) [
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'quantity' => $quantity,
            ]]);
        }

        return BundleItem::where('bundle_product_id', $productId)->get()->map(fn (BundleItem $item) => (object) [
            'product_id' => $item->component_product_id,
            'product_variant_id' => $item->component_variant_id,
            'quantity' => $quantity * $item->quantity,
        ]);
    }

    /**
     * How many of this bundle can currently be sold, per warehouse and in
     * total — the minimum across every component of floor(available /
     * needed), since the bundle itself never holds real stock. A component
     * with no stock_levels row at a warehouse counts as zero there, not as
     * "unconstrained."
     *
     * @return array{total_available: int, by_warehouse: array<int, array{warehouse_id: int, warehouse_name: string, available: int}>}
     */
    public static function availability(int $bundleProductId): array
    {
        $items = BundleItem::where('bundle_product_id', $bundleProductId)->get();

        if ($items->isEmpty()) {
            return ['total_available' => 0, 'by_warehouse' => []];
        }

        $warehouseIds = StockLevel::query()
            ->whereIn('product_id', $items->pluck('component_product_id'))
            ->distinct()
            ->pluck('warehouse_id');

        $byWarehouse = $warehouseIds->map(function (int $warehouseId) use ($items) {
            $available = $items->map(function (BundleItem $item) use ($warehouseId) {
                $level = StockLevel::query()
                    ->where('product_id', $item->component_product_id)
                    ->where('product_variant_id', $item->component_variant_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $componentAvailable = ($level?->quantity ?? 0) - ($level?->quantity_reserved ?? 0);

                return intdiv(max(0, $componentAvailable), $item->quantity);
            })->min();

            return [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => Warehouse::find($warehouseId)?->name ?? 'Unknown warehouse',
                'available' => $available,
            ];
        })->values();

        return [
            'total_available' => (int) $byWarehouse->sum('available'),
            'by_warehouse' => $byWarehouse->all(),
        ];
    }
}
