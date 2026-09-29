<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends StorefrontController
{
    public function index(): JsonResponse
    {
        $store = $this->currentStore();

        // Phase 22: the top-level category tree is read on effectively
        // every storefront browse action and only changes when an admin
        // edits a category — CategoryObserver invalidates this the moment
        // that happens, so the TTL here is just a safety net, not the
        // real invalidation path. Caches the already-resolved plain array,
        // never the raw Eloquent models — confirmed live (not just via the
        // test suite's array-cache driver, which never actually
        // serializes anything) that a cached model/Resource round-trips
        // fine through `artisan tinker` but comes back as an unusable
        // `__PHP_Incomplete_Class` when read back through a real request,
        // because Resources and Eloquent Collections carry framework
        // internals (a request reference, relation loader closures, ...)
        // that plain serialize()/unserialize() can't safely reconstruct.
        $categories = Cache::remember(Category::storefrontCacheKey($store->id), now()->addHour(), function () use ($store) {
            $categories = Category::query()
                ->where('store_id', $store->id)
                ->where('status', 'active')
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return json_decode(json_encode(CategoryResource::collection($categories)), true);
        });

        return ApiResponse::success($categories, 'Categories fetched successfully.');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $category = Category::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with([
                'children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('name'),
                'seoMetadata',
            ])
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
