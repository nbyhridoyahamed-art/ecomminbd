<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BundleItem\BundleItemRequest;
use App\Http\Resources\BundleItemResource;
use App\Models\BundleItem;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages a bundle's own components — a product sub-resource, same
 * `products.update` direct-permission pattern as ProductVariantController
 * and ProductImageController, since a bundle's component list isn't its
 * own policy-guarded resource.
 */
class ProductComponentController extends Controller
{
    private const RELATIONS = ['componentProduct', 'componentVariant'];

    public function store(BundleItemRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        if ($product->type !== 'bundle') {
            return ApiResponse::error('Only a bundle product can have components.', [], 422);
        }

        $data = [
            'bundle_product_id' => $product->id,
            'component_product_id' => $request->validated('product_id'),
            'component_variant_id' => $request->validated('product_variant_id'),
            'quantity' => $request->validated('quantity'),
            'sort_order' => $product->bundleItems()->count(),
        ];

        $item = BundleItem::create($data);

        return ApiResponse::success(new BundleItemResource($item->load(self::RELATIONS)), 'Component added successfully.', status: 201);
    }

    public function update(Request $request, Product $product, BundleItem $component): JsonResponse
    {
        $this->authorize('update', $product);

        if ($component->bundle_product_id !== $product->id) {
            return ApiResponse::error('This component does not belong to this bundle.', [], 404);
        }

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $component->update($data);

        return ApiResponse::success(new BundleItemResource($component->load(self::RELATIONS)), 'Component updated successfully.');
    }

    public function destroy(Product $product, BundleItem $component): JsonResponse
    {
        $this->authorize('update', $product);

        if ($component->bundle_product_id !== $product->id) {
            return ApiResponse::error('This component does not belong to this bundle.', [], 404);
        }

        $component->delete();

        return ApiResponse::success(message: 'Component removed successfully.');
    }
}
