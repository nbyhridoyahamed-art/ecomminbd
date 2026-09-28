<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\ProductDetailResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends StorefrontController
{
    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $perPage = min((int) $request->integer('per_page', 20), 60);

        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->with('images')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)->orWhere('sku', 'like', $term);
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $request->string('category')),
            ))
            ->when($request->filled('brand'), fn ($query) => $query->whereHas(
                'brand',
                fn ($q) => $q->where('slug', $request->string('brand')),
            ))
            ->when($request->boolean('featured'), fn ($query) => $query->where('featured', true))
            ->when($request->filled('sort'), function ($query) use ($request) {
                match ($request->string('sort')->toString()) {
                    'price_asc' => $query->orderBy('price_amount', 'asc'),
                    'price_desc' => $query->orderBy('price_amount', 'desc'),
                    'newest' => $query->latest(),
                    default => $query->orderBy('name'),
                };
            }, fn ($query) => $query->orderBy('name'))
            ->paginate($perPage);

        $this->attachInStock($products->getCollection());

        return ApiResponse::success(
            ProductResource::collection($products),
            'Products fetched successfully.',
            [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with([
                'category', 'brand', 'images',
                'variants.attributeValues.attribute', 'variants.stockLevels',
                'bundleItems.componentProduct',
            ])
            ->firstOrFail();

        $this->attachInStock(collect([$product]));

        foreach ($product->variants as $variant) {
            $variant->in_stock = $variant->stockLevels->sum(fn ($level) => $level->quantity - $level->quantity_reserved) > 0;
        }

        return ApiResponse::success(new ProductDetailResource($product), 'Product fetched successfully.');
    }
}
