<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\SyncsSeoMetadata;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreSeoRequest;
use App\Http\Resources\StoreSeoResource;
use App\Models\Store;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Site-wide SEO (the homepage's own title/description, Organization/WebSite
 * JSON-LD, a fallback OG image) lives on the Store itself via the same
 * seo_metadata table every other entity uses — not a new top-level
 * resource, mirroring how every other entity's SEO is edited as part of
 * its own existing record rather than through a dedicated endpoint. Gated
 * on the seo.manage permission directly (no StorePolicy involvement) since
 * a Content/SEO manager editing site-wide SEO shouldn't need the broader
 * stores.manage permission StoreController's own CRUD requires.
 */
class StoreSeoController extends Controller
{
    use SyncsSeoMetadata;

    public function show(Request $request): JsonResponse
    {
        if (! $request->user()->can('seo.manage')) {
            throw new AuthorizationException;
        }

        $store = Store::query()->with('seoMetadata')->findOrFail($request->integer('store_id'));

        return ApiResponse::success(new StoreSeoResource($store), 'Store SEO fetched successfully.');
    }

    public function update(StoreSeoRequest $request): JsonResponse
    {
        if (! $request->user()->can('seo.manage')) {
            throw new AuthorizationException;
        }

        $store = Store::query()->findOrFail($request->integer('store_id'));
        $this->syncSeoMetadata($store, $request);

        return ApiResponse::success(new StoreSeoResource($store->load('seoMetadata')), 'Store SEO updated successfully.');
    }
}
