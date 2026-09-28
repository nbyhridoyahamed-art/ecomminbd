<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * The tiny slice of the current store a public storefront needs for
 * branding (header logo text, page title) — never the full admin
 * StoreResource, which exposes organization_id, timezone/locale settings,
 * and other operator-internal configuration.
 */
class StoreController extends StorefrontController
{
    public function show(): JsonResponse
    {
        $store = $this->currentStore();

        return ApiResponse::success([
            'name' => $store->name,
            'slug' => $store->slug,
            'currency_code' => $store->currency?->code ?? 'BDT',
        ], 'Store fetched successfully.');
    }
}
