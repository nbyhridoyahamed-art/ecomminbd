<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Requests\Storefront\TrackEventRequest;
use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends StorefrontController
{
    /**
     * Public, unauthenticated, throttled (see routes/api.php) — any
     * storefront visitor's browser can call this, so nothing it stores is
     * trusted as-is: `product_id`/`category_id` are validated to exist
     * before becoming `entity_type`/`entity_id`, and a `purchase` event's
     * `total_amount` is always the real value read back off the matching
     * `orders` row (scoped to this store, by `order_uuid`) rather than
     * anything the client claims — a spoofed `purchase` ping can inflate a
     * conversion *count*, same inherent limitation any client-fired
     * analytics pixel has, but it can never inflate reported *revenue*.
     */
    public function track(TrackEventRequest $request): JsonResponse
    {
        $store = $this->currentStore();
        $eventType = $request->validated('event_type');

        [$entityType, $entityId] = match ($eventType) {
            'product_view', 'add_to_cart', 'remove_from_cart' => $request->filled('product_id')
                ? [Product::class, $request->integer('product_id')]
                : [null, null],
            'category_view' => $request->filled('category_id')
                ? [Category::class, $request->integer('category_id')]
                : [null, null],
            default => [null, null],
        };

        $metadata = match ($eventType) {
            'search' => array_filter([
                'query' => $request->validated('query'),
                'results_count' => $request->input('results_count'),
            ], fn ($value) => $value !== null),
            'purchase' => $this->purchaseMetadata($store->id, $request->validated('order_uuid')),
            default => null,
        };

        AnalyticsEvent::create([
            'store_id' => $store->id,
            'session_id' => $request->validated('session_id'),
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'path' => $request->validated('path'),
            'metadata' => $metadata ?: null,
        ]);

        return ApiResponse::success(message: 'Event tracked.', status: 201);
    }

    /** @return array{order_uuid: string, total_amount: float, currency_code: string}|null */
    private function purchaseMetadata(int $storeId, ?string $orderUuid): ?array
    {
        if (! $orderUuid) {
            return null;
        }

        $order = Order::where('store_id', $storeId)->where('uuid', $orderUuid)->first();

        if (! $order) {
            return null;
        }

        return [
            'order_uuid' => $order->uuid,
            'total_amount' => $order->total_amount,
            'currency_code' => $order->currency_code,
        ];
    }
}
