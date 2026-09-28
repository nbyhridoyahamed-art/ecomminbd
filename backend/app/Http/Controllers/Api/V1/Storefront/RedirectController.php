<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Models\Redirect;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectController extends StorefrontController
{
    /**
     * Checked by a storefront leaf page only after its own by-slug lookup
     * 404s — not global middleware, so a normal request never pays for a
     * redirects table lookup it doesn't need.
     */
    public function lookup(Request $request): JsonResponse
    {
        if (! $request->filled('path')) {
            return ApiResponse::error('The path parameter is required.', [], 422);
        }

        $store = $this->currentStore();

        $redirect = Redirect::query()
            ->where('store_id', $store->id)
            ->where('from_path', $request->string('path'))
            ->first();

        if (! $redirect) {
            return ApiResponse::error('No redirect found for this path.', [], 404);
        }

        $redirect->increment('hits_count');

        return ApiResponse::success([
            'to_path' => $redirect->to_path,
            'status_code' => $redirect->status_code,
        ], 'Redirect found.');
    }
}
