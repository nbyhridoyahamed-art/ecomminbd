<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;

/**
 * Applies one increase/decrease to a product's (or variant's) on-hand stock
 * at a warehouse and returns the resulting ledger row. Shared by the
 * single-shot quick-adjustment endpoint and the multi-line stocktake
 * session endpoint, so both apply the exact same locking/validation/
 * ledger-writing rules. Caller is responsible for wrapping this in its own
 * DB transaction — InsufficientStockException is meant to roll that back.
 */
class StockAdjuster
{
    public static function apply(
        Product $product,
        ?int $productVariantId,
        Warehouse $warehouse,
        string $direction,
        int $quantity,
        ?string $reason,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        // Lets a caller with its own more specific ledger vocabulary (e.g.
        // StockTransferController's transfer_out/transfer_in) keep that
        // distinction in the movements list instead of every mutation
        // reading as a generic "adjustment" — see that controller's ship()/
        // receive() for why the movements ledger's own type filter/labels
        // still need it.
        ?string $movementType = null,
    ): StockMovement {
        $level = StockLevel::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first();

        $before = $level?->quantity ?? 0;
        $delta = $direction === 'increase' ? $quantity : -$quantity;
        $after = $before + $delta;

        if ($after < 0) {
            throw new InsufficientStockException("Not enough stock of \"{$product->name}\" at {$warehouse->name} to decrease by {$quantity} unit(s).");
        }

        // Stock already reserved for pending/processing orders can't be adjusted
        // away, or Order::ship() would later try to decrement on-hand quantity
        // below zero.
        if ($after < ($level?->quantity_reserved ?? 0)) {
            throw new InsufficientStockException("Cannot adjust \"{$product->name}\" at {$warehouse->name} below stock already reserved for pending orders.");
        }

        $level
            ? $level->update(['quantity' => $after])
            : StockLevel::create([
                'product_id' => $product->id,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouse->id,
                'quantity' => $after,
            ]);

        return StockMovement::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouse->id,
            'type' => $movementType ?? ($direction === 'increase' ? 'adjustment_increase' : 'adjustment_decrease'),
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
        ]);
    }
}
