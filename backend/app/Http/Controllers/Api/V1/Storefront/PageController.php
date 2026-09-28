<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\PageResource;
use App\Models\Page;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PageController extends StorefrontController
{
    /** Title/slug only — backs the storefront footer's page links. */
    public function index(): JsonResponse
    {
        $store = $this->currentStore();

        $pages = Page::query()
            ->where('store_id', $store->id)
            ->where('status', 'published')
            ->orderBy('title')
            ->get();

        return ApiResponse::success(PageResource::collection($pages), 'Pages fetched successfully.');
    }

    public function show(string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $page = Page::query()
            ->where('store_id', $store->id)
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with('seoMetadata')
            ->firstOrFail();

        return ApiResponse::success(new PageResource($page), 'Page fetched successfully.');
    }
}
