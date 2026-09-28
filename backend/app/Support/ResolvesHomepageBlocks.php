<?php

namespace App\Support;

use App\Http\Resources\Storefront\BlogPostResource;
use App\Http\Resources\Storefront\BrandResource;
use App\Http\Resources\Storefront\CategoryResource;
use App\Http\Resources\Storefront\ProductResource;
use App\Http\Resources\Storefront\TestimonialResource;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageBlock;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Testimonial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a homepage block's `settings` (auto/manual ids, flash-sale line
 * items, ...) into real, current rows. Shared by
 * Storefront\HomepageController (active blocks only, the public site) and
 * Api\V1\HomepageBlockController::preview() (every block for the current
 * store, draft included, so the admin builder's canvas can render the
 * exact same output the live site will show once a block is published —
 * one resolver, never two implementations that could drift apart).
 */
trait ResolvesHomepageBlocks
{
    /** @return array<string, mixed> */
    protected function resolveBlockData(HomepageBlock $block, int $storeId): array
    {
        return match ($block->type) {
            HomepageBlockTypes::FEATURED_PRODUCTS => [
                'products' => ProductResource::collection($this->resolveProducts($block, $storeId, fn ($q) => $q->where('featured', true)->latest())),
            ],
            HomepageBlockTypes::LATEST_PRODUCTS => [
                'products' => ProductResource::collection($this->resolveProducts($block, $storeId, fn ($q) => $q->latest(), autoOnly: true)),
            ],
            HomepageBlockTypes::BEST_SELLERS => [
                'products' => ProductResource::collection($this->bestSellingProducts($storeId, $block->settings['limit'] ?? 8)),
            ],
            HomepageBlockTypes::PRODUCT_CAROUSEL => [
                'products' => ProductResource::collection($this->resolveProductCarousel($block, $storeId)),
            ],
            HomepageBlockTypes::CATEGORY_GRID, HomepageBlockTypes::CATEGORY_CAROUSEL => [
                'categories' => CategoryResource::collection($this->resolveByIdsOrAuto(
                    Category::query()->where('store_id', $storeId)->where('status', 'active'),
                    $block->settings['mode'] ?? 'auto',
                    $block->settings['category_ids'] ?? [],
                    $block->settings['limit'] ?? 6,
                    fn ($q) => $q->whereNull('parent_id')->orderBy('sort_order'),
                )),
            ],
            HomepageBlockTypes::BRAND_CAROUSEL => [
                'brands' => BrandResource::collection($this->resolveByIdsOrAuto(
                    Brand::query()->where('store_id', $storeId),
                    $block->settings['mode'] ?? 'auto',
                    $block->settings['brand_ids'] ?? [],
                    $block->settings['limit'] ?? 8,
                    fn ($q) => $q->orderBy('name'),
                )),
            ],
            HomepageBlockTypes::TESTIMONIALS, HomepageBlockTypes::REVIEWS => [
                'testimonials' => TestimonialResource::collection($this->resolveByIdsOrAuto(
                    Testimonial::query()->where('store_id', $storeId)->where('is_active', true),
                    $block->settings['mode'] ?? 'auto',
                    $block->settings['testimonial_ids'] ?? [],
                    $block->settings['limit'] ?? 3,
                    fn ($q) => $q->orderBy('sort_order'),
                )),
            ],
            HomepageBlockTypes::BLOG_POSTS => [
                'posts' => BlogPostResource::collection(
                    BlogPost::query()
                        ->where('store_id', $storeId)
                        ->where('is_active', true)
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->latest('published_at')
                        ->limit($block->settings['limit'] ?? 3)
                        ->get()
                ),
            ],
            HomepageBlockTypes::FLASH_SALE => [
                'items' => collect($block->settings['items'] ?? [])
                    ->map(function ($item) use ($storeId) {
                        $product = Product::query()->where('store_id', $storeId)->where('status', 'active')->with('images')->find($item['product_id']);
                        if (! $product) {
                            return null;
                        }
                        $this->attachInStockForResolver(Collection::make([$product]));

                        return [
                            'product' => new ProductResource($product),
                            'sale_price' => (new Money($item['sale_price'], $product->currency_code))->toDecimal(),
                        ];
                    })
                    ->filter()
                    ->values(),
            ],
            default => [],
        };
    }

    /**
     * Shared "mode: auto|manual" resolution — manual keeps the staff-picked
     * order from `$ids`, auto falls back to the caller's default ordering.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  array<int, int>  $ids
     */
    private function resolveByIdsOrAuto($query, string $mode, array $ids, int $limit, \Closure $autoOrder): Collection
    {
        if ($mode === 'manual' && count($ids) > 0) {
            $rows = (clone $query)->whereIn('id', $ids)->get()->keyBy('id');

            return Collection::make($ids)->map(fn ($id) => $rows->get($id))->filter()->values();
        }

        return $autoOrder($query)->limit($limit)->get();
    }

    private function resolveProducts(HomepageBlock $block, int $storeId, \Closure $autoScope, bool $autoOnly = false): Collection
    {
        $base = Product::query()->where('store_id', $storeId)->where('status', 'active')->with('images');
        $mode = $autoOnly ? 'auto' : ($block->settings['mode'] ?? 'auto');

        $products = $this->resolveByIdsOrAuto($base, $mode, $block->settings['product_ids'] ?? [], $block->settings['limit'] ?? 8, $autoScope);
        $this->attachInStockForResolver($products);

        return $products;
    }

    private function resolveProductCarousel(HomepageBlock $block, int $storeId): Collection
    {
        $base = Product::query()->where('store_id', $storeId)->where('status', 'active')->with('images');
        $mode = $block->settings['mode'] ?? 'auto';
        $limit = $block->settings['limit'] ?? 10;

        $products = match ($mode) {
            'manual' => $this->resolveByIdsOrAuto($base, 'manual', $block->settings['product_ids'] ?? [], $limit, fn ($q) => $q),
            'category' => (clone $base)->whereHas('category', fn ($q) => $q->where('id', $block->settings['category_id'] ?? 0))->latest()->limit($limit)->get(),
            default => (clone $base)->latest()->limit($limit)->get(),
        };

        $this->attachInStockForResolver($products);

        return $products;
    }

    private function bestSellingProducts(int $storeId, int $limit): Collection
    {
        $ranked = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units_sold')
            ->groupBy('order_items.product_id')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->pluck('product_id');

        $products = Product::query()->where('store_id', $storeId)->where('status', 'active')->with('images')->whereIn('id', $ranked)->get()->keyBy('id');
        $ordered = $ranked->map(fn ($id) => $products->get($id))->filter()->values();
        $this->attachInStockForResolver($ordered);

        return $ordered;
    }

    /**
     * Mirrors StorefrontController::attachInStock() — duplicated rather
     * than inherited since this trait is also used by an admin controller
     * that isn't (and shouldn't become) a StorefrontController subclass.
     */
    private function attachInStockForResolver(Collection $products): void
    {
        $nonBundleIds = $products->where('type', '!=', 'bundle')->pluck('id');

        $availableByProduct = StockLevel::query()
            ->whereIn('product_id', $nonBundleIds)
            ->selectRaw('product_id, SUM(quantity - quantity_reserved) as available')
            ->groupBy('product_id')
            ->pluck('available', 'product_id');

        foreach ($products as $product) {
            $product->in_stock = $product->type === 'bundle'
                ? BundleExpander::availability($product->id)['total_available'] > 0
                : ($availableByProduct[$product->id] ?? 0) > 0;
        }
    }
}
