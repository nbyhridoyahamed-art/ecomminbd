<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Models\HomepageBlock;
use App\Support\ApiResponse;
use App\Support\ResolvesHomepageBlocks;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves every live homepage block into render-ready data for the public
 * storefront — see ResolvesHomepageBlocks for the per-type resolution
 * itself, shared with the admin builder's own preview endpoint.
 */
class HomepageController extends StorefrontController
{
    use ResolvesHomepageBlocks;

    public function index(): JsonResponse
    {
        $store = $this->currentStore();

        // Phase 22: this is the single highest-traffic page on the whole
        // storefront, and per-block resolution (up to ~30 block types, each
        // its own query) previously re-ran on every single view.
        // HomepageBlockController invalidates this the moment any admin
        // action could change what's live (publish/unpublish/reorder/edit/
        // delete/duplicate/restore) — the TTL here is only a safety net.
        // Caches the resolved plain array, never the raw Resources
        // resolveBlockData() returns (ProductResource::collection() and
        // friends, each still wrapping live Eloquent models) — see
        // CategoryController::index() for why that's not just a style
        // choice: it's the difference between working and a
        // __PHP_Incomplete_Class error on every cache hit outside the
        // test suite's non-serializing array cache driver.
        $resolved = Cache::remember(HomepageBlock::storefrontCacheKey($store->id), now()->addHour(), function () use ($store) {
            $blocks = HomepageBlock::query()
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $data = $blocks->map(fn (HomepageBlock $block) => [
                'id' => $block->id,
                'type' => $block->type,
                'settings' => $block->settings,
                'styles' => $block->styles ?? (object) [],
                'responsive' => $block->responsive ?? (object) [],
                'visibility' => $block->visibility ?? (object) [],
                'animation' => $block->animation,
                'data' => $this->resolveBlockData($block, $store->id),
            ])->all();

            return json_decode(json_encode($data), true);
        });

        return ApiResponse::success($resolved, 'Homepage blocks fetched successfully.');
    }
}
