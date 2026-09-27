<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    private const RELATIONS = ['division', 'district', 'upazila'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $warehouses = Warehouse::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            WarehouseResource::collection($warehouses),
            'Warehouses fetched successfully.',
            [
                'current_page' => $warehouses->currentPage(),
                'per_page' => $warehouses->perPage(),
                'total' => $warehouses->total(),
                'last_page' => $warehouses->lastPage(),
            ],
        );
    }

    public function store(WarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = Warehouse::create($request->validated());

        return ApiResponse::success(new WarehouseResource($warehouse->load(self::RELATIONS)), 'Warehouse created successfully.', status: 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        return ApiResponse::success(new WarehouseResource($warehouse->load(self::RELATIONS)), 'Warehouse fetched successfully.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse->update($request->validated());

        return ApiResponse::success(new WarehouseResource($warehouse->load(self::RELATIONS)), 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return ApiResponse::success(message: 'Warehouse deleted successfully.');
    }
}
