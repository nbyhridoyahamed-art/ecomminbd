<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        if (! $request->user()->can('inventory.adjust')) {
            throw new AuthorizationException;
        }

        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        if ($product->store_id !== $warehouse->store_id) {
            return ApiResponse::error('The product and warehouse must belong to the same store.', [], 422);
        }

        try {
            $movement = DB::transaction(function () use ($data, $product, $warehouse, $request) {
                $level = StockLevel::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->first();

                $before = $level?->quantity ?? 0;
                $delta = $data['direction'] === 'increase' ? $data['quantity'] : -$data['quantity'];
                $after = $before + $delta;

                if ($after < 0) {
                    throw new InsufficientStockException('Not enough stock at this warehouse to decrease by that amount.');
                }

                if ($level) {
                    $level->update(['quantity' => $after]);
                } else {
                    StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $after]);
                }

                return StockMovement::create([
                    'store_id' => $product->store_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => $data['direction'] === 'increase' ? 'adjustment_increase' : 'adjustment_decrease',
                    'quantity' => $data['quantity'],
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => $data['reason'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new StockMovementResource($movement->load(['product', 'warehouse', 'creator'])),
            'Stock adjusted successfully.',
            status: 201,
        );
    }
}
