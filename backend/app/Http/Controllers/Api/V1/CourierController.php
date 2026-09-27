<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\CourierRequest;
use App\Http\Resources\CourierResource;
use App\Models\Courier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Courier::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $couriers = Courier::query()
            ->withCount('shipments')
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::success(
            CourierResource::collection($couriers),
            'Couriers fetched successfully.',
            [
                'current_page' => $couriers->currentPage(),
                'per_page' => $couriers->perPage(),
                'total' => $couriers->total(),
                'last_page' => $couriers->lastPage(),
            ],
        );
    }

    public function store(CourierRequest $request): JsonResponse
    {
        $this->authorize('create', Courier::class);

        $data = $request->validated();
        $data['status'] ??= 'active';

        $courier = Courier::create($data);

        return ApiResponse::success(new CourierResource($courier), 'Courier created successfully.', status: 201);
    }

    public function show(Courier $courier): JsonResponse
    {
        $this->authorize('view', $courier);

        return ApiResponse::success(new CourierResource($courier->loadCount('shipments')), 'Courier fetched successfully.');
    }

    public function update(CourierRequest $request, Courier $courier): JsonResponse
    {
        $this->authorize('update', $courier);

        $courier->update($request->validated());

        return ApiResponse::success(new CourierResource($courier), 'Courier updated successfully.');
    }

    public function destroy(Courier $courier): JsonResponse
    {
        $this->authorize('delete', $courier);

        $courier->delete();

        return ApiResponse::success(message: 'Courier deleted successfully.');
    }
}
