<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Models\Product;
use App\Models\StockLevel;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockLevelController extends Controller
{
    /**
     * Current on-hand quantity per product at one warehouse. Products
     * without a stock_levels row yet (nothing has moved for them there)
     * still show up with quantity 0 via the left join. "Low stock" (and
     * the low_stock filter) compares against *available* stock
     * (quantity - quantity_reserved), not raw on-hand quantity — once
     * Phase 8 orders reserve stock, on-hand alone overstates what's
     * actually sellable.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            throw new AuthorizationException;
        }

        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
        ]);

        $storeId = $request->integer('store_id');
        $warehouseId = $request->integer('warehouse_id');
        $perPage = min((int) $request->integer('per_page', 20), 100);
        $lowStockOnly = $request->boolean('low_stock');

        $products = Product::query()
            ->leftJoin('stock_levels', function ($join) use ($warehouseId) {
                $join->on('stock_levels.product_id', '=', 'products.id')
                    ->where('stock_levels.warehouse_id', '=', $warehouseId);
            })
            ->where('products.store_id', $storeId)
            ->select(
                'products.*',
                DB::raw('COALESCE(stock_levels.quantity, 0) as warehouse_quantity'),
                DB::raw('COALESCE(stock_levels.quantity_reserved, 0) as warehouse_reserved'),
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('products.name', 'like', $term)->orWhere('products.sku', 'like', $term);
                });
            })
            ->when($lowStockOnly, fn ($query) => $query
                ->where('products.track_stock', true)
                ->whereNotNull('products.low_stock_threshold')
                ->whereRaw('(COALESCE(stock_levels.quantity, 0) - COALESCE(stock_levels.quantity_reserved, 0)) <= products.low_stock_threshold'))
            ->orderBy('products.name')
            ->paginate($perPage);

        return ApiResponse::success(
            StockLevelResource::collection($products),
            'Stock levels fetched successfully.',
            [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        );
    }

    /**
     * Count of (product, warehouse) pairs currently at or below their
     * product's low_stock_threshold — comparing *available* stock
     * (quantity - quantity_reserved), across every warehouse in the
     * store — backs the dashboard's "Low stock alerts" KPI.
     */
    public function lowStockCount(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);

        $count = StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('products.store_id', $request->integer('store_id'))
            ->where('products.track_stock', true)
            ->whereNotNull('products.low_stock_threshold')
            ->whereRaw('(stock_levels.quantity - stock_levels.quantity_reserved) <= products.low_stock_threshold')
            ->count();

        return ApiResponse::success(['count' => $count], 'Low stock count fetched successfully.');
    }
}
