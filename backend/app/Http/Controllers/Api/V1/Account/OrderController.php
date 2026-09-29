<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\Account\OrderResource;
use App\Models\Order;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    private const RELATIONS = [
        'items.product', 'items.productVariant.attributeValues.attribute',
        'shippingDivision', 'shippingDistrict', 'shippingUpazila', 'statusHistory',
    ];

    /**
     * Always scoped to the authenticated customer's own id — never a
     * client-supplied customer_id, unlike the admin GET /orders.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 20), 50);

        $orders = Order::query()
            ->where('customer_id', Auth::id())
            ->with(self::RELATIONS)
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            OrderResource::collection($orders),
            'Orders fetched successfully.',
            [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        );
    }

    /**
     * Looked up by uuid, scoped to the authenticated customer — a 404
     * (never a 403) for an order that exists but belongs to someone
     * else, so this can't be used to probe which uuids are real.
     */
    public function show(string $uuid): JsonResponse
    {
        $order = Order::query()
            ->where('uuid', $uuid)
            ->where('customer_id', Auth::id())
            ->with(self::RELATIONS)
            ->firstOrFail();

        // Computed once here (not per-item in the Resource) to avoid an
        // N+1 — the same "derive it in bulk in the controller, just read it
        // in the Resource" convention Storefront\ProductController's
        // attachInStock() uses.
        if ($order->status === 'delivered') {
            $reviewedProductIds = Review::where('customer_id', Auth::id())
                ->whereIn('product_id', $order->items->pluck('product_id'))
                ->pluck('product_id');

            foreach ($order->items as $item) {
                $item->reviewable = ! $reviewedProductIds->contains($item->product_id);
            }
        } else {
            foreach ($order->items as $item) {
                $item->reviewable = false;
            }
        }

        return ApiResponse::success(new OrderResource($order), 'Order fetched successfully.');
    }
}
