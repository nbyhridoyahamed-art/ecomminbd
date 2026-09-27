<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $movements = StockMovement::query()
            ->with(['product', 'warehouse', 'creator'])
            ->where('store_id', $request->integer('store_id'))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            StockMovementResource::collection($movements),
            'Stock movements fetched successfully.',
            [
                'current_page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'last_page' => $movements->lastPage(),
            ],
        );
    }
}
