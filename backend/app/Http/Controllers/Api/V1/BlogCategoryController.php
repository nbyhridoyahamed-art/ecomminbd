<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogCategory\BlogCategoryRequest;
use App\Http\Resources\BlogCategoryResource;
use App\Models\BlogCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BlogCategory::class);

        $categories = BlogCategory::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(BlogCategoryResource::collection($categories), 'Blog categories fetched successfully.');
    }

    public function store(BlogCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', BlogCategory::class);

        $category = BlogCategory::create($request->validated());

        return ApiResponse::success(new BlogCategoryResource($category), 'Blog category created successfully.', status: 201);
    }

    public function show(BlogCategory $blogCategory): JsonResponse
    {
        $this->authorize('view', $blogCategory);

        return ApiResponse::success(new BlogCategoryResource($blogCategory->loadCount('posts')), 'Blog category fetched successfully.');
    }

    public function update(BlogCategoryRequest $request, BlogCategory $blogCategory): JsonResponse
    {
        $this->authorize('update', $blogCategory);

        $blogCategory->update($request->validated());

        return ApiResponse::success(new BlogCategoryResource($blogCategory), 'Blog category updated successfully.');
    }

    public function destroy(BlogCategory $blogCategory): JsonResponse
    {
        $this->authorize('delete', $blogCategory);

        $blogCategory->delete();

        return ApiResponse::success(message: 'Blog category deleted successfully.');
    }
}
