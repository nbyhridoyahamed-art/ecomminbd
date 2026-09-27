<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const RELATIONS = ['category', 'brand', 'images'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $products = Product::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            }))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($perPage);

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

    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $this->preparePayload($request);
        // Set explicitly rather than leaning on the migration's column
        // defaults: Product::create() returns the in-memory model built
        // from $data, not a fresh SELECT, so any field left for the DB
        // default to fill in would come back null in this response.
        $data['type'] ??= 'simple';
        $data['status'] ??= 'draft';
        $data['track_stock'] ??= true;
        $data['featured'] ??= false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        if ($data['status'] === 'active') {
            $data['published_at'] = now();
        }

        $product = Product::create($data);

        return ApiResponse::success(new ProductResource($product->load(self::RELATIONS)), 'Product created successfully.', status: 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return ApiResponse::success(new ProductResource($product->load(self::RELATIONS)), 'Product fetched successfully.');
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $this->preparePayload($request);
        $data['updated_by'] = $request->user()->id;
        if (($data['status'] ?? null) === 'active' && $product->published_at === null) {
            $data['published_at'] = now();
        }

        $product->update($data);

        return ApiResponse::success(new ProductResource($product->load(self::RELATIONS)), 'Product updated successfully.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return ApiResponse::success(message: 'Product deleted successfully.');
    }

    /**
     * Converts the request's decimal money fields to the minor-unit columns
     * the model actually stores, and drops the decimal-named keys so they
     * never reach a mass-assignment call.
     */
    private function preparePayload(ProductRequest $request): array
    {
        $data = $request->validated();
        $currency = $data['currency_code'] ?? 'BDT';

        $data['currency_code'] = $currency;
        $data['price_amount'] = Money::fromDecimal($data['price'], $currency)->amountMinor;
        $data['sale_price_amount'] = Money::fromDecimal($data['sale_price'] ?? null, $currency)?->amountMinor;
        $data['cost_price_amount'] = Money::fromDecimal($data['cost_price'] ?? null, $currency)?->amountMinor;
        $data['compare_at_price_amount'] = Money::fromDecimal($data['compare_at_price'] ?? null, $currency)?->amountMinor;

        unset($data['price'], $data['sale_price'], $data['cost_price'], $data['compare_at_price']);

        return $data;
    }
}
