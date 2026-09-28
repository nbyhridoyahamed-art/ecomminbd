<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Testimonial\TestimonialRequest;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Testimonial::class);

        $testimonials = Testimonial::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(TestimonialResource::collection($testimonials), 'Testimonials fetched successfully.');
    }

    public function store(TestimonialRequest $request): JsonResponse
    {
        $this->authorize('create', Testimonial::class);

        $testimonial = Testimonial::create($request->validated());

        return ApiResponse::success(new TestimonialResource($testimonial), 'Testimonial created successfully.', status: 201);
    }

    public function show(Testimonial $testimonial): JsonResponse
    {
        $this->authorize('view', $testimonial);

        return ApiResponse::success(new TestimonialResource($testimonial), 'Testimonial fetched successfully.');
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): JsonResponse
    {
        $this->authorize('update', $testimonial);

        $testimonial->update($request->validated());

        return ApiResponse::success(new TestimonialResource($testimonial), 'Testimonial updated successfully.');
    }

    public function destroy(Testimonial $testimonial): JsonResponse
    {
        $this->authorize('delete', $testimonial);

        $testimonial->delete();

        return ApiResponse::success(message: 'Testimonial deleted successfully.');
    }
}
