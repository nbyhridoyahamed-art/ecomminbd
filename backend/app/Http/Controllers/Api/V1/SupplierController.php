<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::success(
            SupplierResource::collection($suppliers),
            'Suppliers fetched successfully.',
            [
                'current_page' => $suppliers->currentPage(),
                'per_page' => $suppliers->perPage(),
                'total' => $suppliers->total(),
                'last_page' => $suppliers->lastPage(),
            ],
        );
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $data = $request->validated();
        $data['status'] ??= 'active';

        $supplier = Supplier::create($data);

        return ApiResponse::success(new SupplierResource($supplier), 'Supplier created successfully.', status: 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return ApiResponse::success(new SupplierResource($supplier->loadCount('purchaseOrders')), 'Supplier fetched successfully.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return ApiResponse::success(new SupplierResource($supplier), 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return ApiResponse::success(message: 'Supplier deleted successfully.');
    }
}
