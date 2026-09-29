<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use App\Support\StockAdjuster;
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
            $movement = DB::transaction(fn () => StockAdjuster::apply(
                product: $product,
                productVariantId: $data['product_variant_id'] ?? null,
                warehouse: $warehouse,
                direction: $data['direction'],
                quantity: $data['quantity'],
                reason: $data['reason'] ?? null,
                userId: $request->user()->id,
            ));
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new StockMovementResource($movement->load(['product', 'productVariant.attributeValues.attribute', 'warehouse', 'creator'])),
            'Stock adjusted successfully.',
            status: 201,
        );
    }
}
