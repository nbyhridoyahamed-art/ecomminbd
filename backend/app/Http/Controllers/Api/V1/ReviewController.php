<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const RELATIONS = ['customer', 'product'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Review::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $reviews = Review::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', $request->integer('rating')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            ReviewResource::collection($reviews),
            'Reviews fetched successfully.',
            [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        );
    }

    public function show(Review $review): JsonResponse
    {
        $this->authorize('view', $review);

        return ApiResponse::success(new ReviewResource($review->load(self::RELATIONS)), 'Review fetched successfully.');
    }

    public function approve(Review $review): JsonResponse
    {
        $this->authorize('moderate', $review);

        $review->update(['status' => 'approved']);

        return ApiResponse::success(new ReviewResource($review->load(self::RELATIONS)), 'Review approved.');
    }

    public function reject(Review $review): JsonResponse
    {
        $this->authorize('moderate', $review);

        $review->update(['status' => 'rejected']);

        return ApiResponse::success(new ReviewResource($review->load(self::RELATIONS)), 'Review rejected.');
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return ApiResponse::success(message: 'Review deleted successfully.');
    }
}
