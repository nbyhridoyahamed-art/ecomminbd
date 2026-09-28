<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends StorefrontController
{
    public function index(): JsonResponse
    {
        $store = $this->currentStore();

        $categories = Category::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(CategoryResource::collection($categories), 'Categories fetched successfully.');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $category = Category::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('name')])
            ->firstOrFail();

        $perPage = min((int) $request->integer('per_page', 20), 60);

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('status', 'active')
            ->with('images')
            ->orderBy('name')
            ->paginate($perPage);

        $this->attachInStock($products->getCollection());

        return ApiResponse::success(
            [
                'category' => new CategoryResource($category),
                'products' => ProductResource::collection($products),
            ],
            'Category fetched successfully.',
            [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        );
    }
}
