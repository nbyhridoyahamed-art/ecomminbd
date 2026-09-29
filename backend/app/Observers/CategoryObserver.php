<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the storefront's cached category tree (Phase 22) on any
 * write — `saved` covers both create and update, and a category never
 * moves between stores, so its current store_id is always the right key
 * to bust, before or after the change.
 */
class CategoryObserver
{
    public function saved(Category $category): void
    {
        Cache::forget(Category::storefrontCacheKey($category->store_id));
    }

    public function deleted(Category $category): void
    {
        Cache::forget(Category::storefrontCacheKey($category->store_id));
    }
}
