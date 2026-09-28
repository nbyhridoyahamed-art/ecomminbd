<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Models\HomepageBlock;
use App\Support\ApiResponse;
use App\Support\ResolvesHomepageBlocks;
use Illuminate\Http\JsonResponse;

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

        $blocks = HomepageBlock::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $resolved = $blocks->map(fn (HomepageBlock $block) => [
            'id' => $block->id,
            'type' => $block->type,
            'settings' => $block->settings,
            'styles' => $block->styles ?? (object) [],
            'responsive' => $block->responsive ?? (object) [],
            'visibility' => $block->visibility ?? (object) [],
            'animation' => $block->animation,
            'data' => $this->resolveBlockData($block, $store->id),
        ]);

        return ApiResponse::success($resolved, 'Homepage blocks fetched successfully.');
    }
}
