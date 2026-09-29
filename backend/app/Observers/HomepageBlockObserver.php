<?php

namespace App\Observers;

use App\Models\HomepageBlock;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the storefront's cached resolved homepage (Phase 22) on any
 * write. Covers store/update/destroy/publish/unpublish/duplicate (its own
 * create() call)/restore in HomepageBlockController, and
 * PublishScheduledHomepageBlocks — every one of them mutates through a
 * model instance. The one write path that doesn't, reorder()'s per-ID
 * query-builder update, invalidates explicitly in the controller instead,
 * since a query-builder update never fires model events for this (or any)
 * observer to catch.
 */
class HomepageBlockObserver
{
    public function saved(HomepageBlock $block): void
    {
        Cache::forget(HomepageBlock::storefrontCacheKey($block->store_id));
    }

    public function deleted(HomepageBlock $block): void
    {
        Cache::forget(HomepageBlock::storefrontCacheKey($block->store_id));
    }
}
