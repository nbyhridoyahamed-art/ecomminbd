<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * The authenticated customer's own reviews, any status — unlike the
     * storefront listing, which only ever shows approved ones.
     */
    public function index(): JsonResponse
    {
        $reviews = Review::query()
            ->where('customer_id', Auth::id())
            ->with('product')
            ->latest()
            ->get();

        return ApiResponse::success(ReviewResource::collection($reviews), 'Your reviews fetched successfully.');
    }

    /**
     * Verified-purchase gated: the customer must have a delivered order
     * containing this product. Which order proves it is resolved here,
     * server-side, from the customer's own real order history — never a
     * client-supplied order_id, so this can't be forged by naming someone
     * else's order. One review per (customer, product) regardless of how
     * many qualifying orders exist, matching the table's own unique index.
     */
    public function store(ReviewRequest $request): JsonResponse
    {
        $productId = $request->validated('product_id');

        if (Review::where('customer_id', Auth::id())->where('product_id', $productId)->exists()) {
            return ApiResponse::error('You have already reviewed this product.', [], 422);
        }

        $order = Order::query()
            ->where('customer_id', Auth::id())
            ->where('status', 'delivered')
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->first();

        if (! $order) {
            return ApiResponse::error('You can only review a product from a delivered order.', [], 422);
        }

        $review = Review::create([
            'store_id' => $order->store_id,
            'product_id' => $productId,
            'customer_id' => Auth::id(),
            'order_id' => $order->id,
            'rating' => $request->validated('rating'),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'status' => 'pending',
        ]);

        return ApiResponse::success(new ReviewResource($review->load('product')), 'Review submitted — it will appear once approved.', status: 201);
    }
}
