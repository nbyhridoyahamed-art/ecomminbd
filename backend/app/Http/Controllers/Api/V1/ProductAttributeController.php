<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductAttribute\ProductAttributeRequest;
use App\Http\Requests\ProductAttribute\ProductAttributeValueRequest;
use App\Http\Resources\ProductAttributeResource;
use App\Http\Resources\ProductAttributeValueResource;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAttributeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductAttribute::class);

        $attributes = ProductAttribute::query()
            ->with('values')
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ProductAttributeResource::collection($attributes), 'Attributes fetched successfully.');
    }

    public function store(ProductAttributeRequest $request): JsonResponse
    {
        $this->authorize('create', ProductAttribute::class);

        $attribute = ProductAttribute::create($request->validated());

        return ApiResponse::success(new ProductAttributeResource($attribute->load('values')), 'Attribute created successfully.', status: 201);
    }

    public function show(ProductAttribute $productAttribute): JsonResponse
    {
        $this->authorize('view', $productAttribute);

        return ApiResponse::success(new ProductAttributeResource($productAttribute->load('values')), 'Attribute fetched successfully.');
    }

    public function update(ProductAttributeRequest $request, ProductAttribute $productAttribute): JsonResponse
    {
        $this->authorize('update', $productAttribute);

        $productAttribute->update($request->validated());

        return ApiResponse::success(new ProductAttributeResource($productAttribute->load('values')), 'Attribute updated successfully.');
    }

    public function destroy(ProductAttribute $productAttribute): JsonResponse
    {
        $this->authorize('delete', $productAttribute);

        if ($this->hasVariantsUsingAnyValue($productAttribute)) {
            return ApiResponse::error('Remove this attribute from every product variant first.', [], 422);
        }

        $productAttribute->delete();

        return ApiResponse::success(message: 'Attribute deleted successfully.');
    }

    public function storeValue(ProductAttributeValueRequest $request, ProductAttribute $productAttribute): JsonResponse
    {
        $this->authorize('update', $productAttribute);

        $data = $request->validated();
        $data['sort_order'] ??= $productAttribute->values()->count();

        $value = $productAttribute->values()->create($data);

        return ApiResponse::success(new ProductAttributeValueResource($value), 'Value added successfully.', status: 201);
    }

    public function updateValue(ProductAttributeValueRequest $request, ProductAttribute $productAttribute, ProductAttributeValue $value): JsonResponse
    {
        $this->authorize('update', $productAttribute);

        if ($value->product_attribute_id !== $productAttribute->id) {
            return ApiResponse::error('This value does not belong to this attribute.', [], 404);
        }

        $value->update($request->validated());

        return ApiResponse::success(new ProductAttributeValueResource($value), 'Value updated successfully.');
    }

    public function destroyValue(ProductAttribute $productAttribute, ProductAttributeValue $value): JsonResponse
    {
        $this->authorize('update', $productAttribute);

        if ($value->product_attribute_id !== $productAttribute->id) {
            return ApiResponse::error('This value does not belong to this attribute.', [], 404);
        }

        if ($value->variants()->exists()) {
            return ApiResponse::error('Remove this value from every product variant first.', [], 422);
        }

        $value->delete();

        return ApiResponse::success(message: 'Value deleted successfully.');
    }

    private function hasVariantsUsingAnyValue(ProductAttribute $productAttribute): bool
    {
        return ProductAttributeValue::query()
            ->where('product_attribute_id', $productAttribute->id)
            ->whereHas('variants')
            ->exists();
    }
}
