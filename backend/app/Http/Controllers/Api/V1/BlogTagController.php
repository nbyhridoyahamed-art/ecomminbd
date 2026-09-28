<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogTag\BlogTagRequest;
use App\Http\Resources\BlogTagResource;
use App\Models\BlogTag;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BlogTag::class);

        $tags = BlogTag::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->withCount('posts')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(BlogTagResource::collection($tags), 'Blog tags fetched successfully.');
    }

    public function store(BlogTagRequest $request): JsonResponse
    {
        $this->authorize('create', BlogTag::class);

        $tag = BlogTag::create($request->validated());

        return ApiResponse::success(new BlogTagResource($tag), 'Blog tag created successfully.', status: 201);
    }

    public function show(BlogTag $blogTag): JsonResponse
    {
        $this->authorize('view', $blogTag);

        return ApiResponse::success(new BlogTagResource($blogTag->loadCount('posts')), 'Blog tag fetched successfully.');
    }

    public function update(BlogTagRequest $request, BlogTag $blogTag): JsonResponse
    {
        $this->authorize('update', $blogTag);

        $blogTag->update($request->validated());

        return ApiResponse::success(new BlogTagResource($blogTag), 'Blog tag updated successfully.');
    }

    public function destroy(BlogTag $blogTag): JsonResponse
    {
        $this->authorize('delete', $blogTag);

        $blogTag->delete();

        return ApiResponse::success(message: 'Blog tag deleted successfully.');
    }
}
