<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;

/**
 * Shared item-snapshot + stock-reservation logic for creating an order —
 * used by both the admin OrderController and the public storefront
 * checkout (StorefrontCheckoutController), so a bundle-snapshot or
 * reservation-guard change can't drift out of sync between the two entry
 * points. Callers are responsible for authoritative pricing: admin trusts
 * the submitted unit_price (staff can legitimately override it), the
 * storefront must build $items from server-computed prices instead.
 */
final class OrderPlacement
{
    /**
     * Replaces an order's items wholesale and resolves+snapshots each
     * item's components (BundleExpander) into order_item_components: an
     * identity row for a simple/variable product, or one row per bundle
     * component. reserveItems()/OrderController's releaseReservation()/
     * ship() all read this snapshot rather than re-deriving it from the
     * bundle's live composition, so editing a bundle's components later
     * can't split one order between two different resolutions.
     */
    public static function syncItems(Order $order, array $items, string $currency): void
    {
        foreach ($items as $item) {
            $orderItem = $order->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price_amount' => Money::fromDecimal($item['unit_price'], $currency)->amountMinor,
            ]);

            foreach (BundleExpander::expand($orderItem->product_id, $orderItem->product_variant_id, $orderItem->quantity) as $component) {
                $orderItem->components()->create([
                    'product_id' => $component->product_id,
                    'product_variant_id' => $component->product_variant_id,
                    'quantity' => $component->quantity,
                ]);
            }
        }
    }

    /** Reserves stock for every item's resolved components, atomically, at the order's current warehouse. */
    public static function reserveItems(Order $order): void
    {
        foreach ($order->items()->with('components')->get() as $item) {
            foreach ($item->resolvedComponents() as $component) {
                $level = StockLevel::query()
                    ->where('product_id', $component->product_id)
                    ->where('product_variant_id', $component->product_variant_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                $available = ($level?->quantity ?? 0) - ($level?->quantity_reserved ?? 0);

                if ($available < $component->quantity) {
                    $product = Product::findOrFail($component->product_id);
                    throw new InsufficientStockException("Not enough available stock of \"{$product->name}\" at this warehouse to fulfil {$component->quantity} unit(s).");
                }

                $level->update(['quantity_reserved' => $level->quantity_reserved + $component->quantity]);
            }
        }
    }
}
