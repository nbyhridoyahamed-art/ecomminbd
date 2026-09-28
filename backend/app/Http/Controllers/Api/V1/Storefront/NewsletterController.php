<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Requests\Storefront\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class NewsletterController extends StorefrontController
{
    public function subscribe(NewsletterSubscribeRequest $request): JsonResponse
    {
        $store = $this->currentStore();

        // Idempotent by design — resubscribing with the same email is a
        // no-op success, not a validation error a shopper would need to
        // puzzle over on a marketing form.
        NewsletterSubscriber::firstOrCreate([
            'store_id' => $store->id,
            'email' => $request->validated('email'),
        ], [
            'subscribed_at' => now(),
        ]);

        return ApiResponse::success(message: 'Subscribed successfully.', status: 201);
    }
}
