<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\BrandResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Brand;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends StorefrontController
{
    public function index(): JsonResponse
    {
        $store = $this->currentStore();

        $brands = Brand::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(BrandResource::collection($brands), 'Brands fetched successfully.');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $brand = Brand::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->where('slug', $slug)
            ->firstOrFail();

        $perPage = min((int) $request->integer('per_page', 20), 60);

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->where('status', 'active')
            ->with('images')
            ->orderBy('name')
            ->paginate($perPage);

        $this->attachInStock($products->getCollection());

        return ApiResponse::success(
            [
                'brand' => new BrandResource($brand),
                'products' => ProductResource::collection($products),
            ],
            'Brand fetched successfully.',
            [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        );
    }
}
