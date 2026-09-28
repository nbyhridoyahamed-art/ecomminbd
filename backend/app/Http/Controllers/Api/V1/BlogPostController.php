<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlogPost\BlogPostRequest;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = BlogPost::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->orderByDesc('published_at')
            ->get();

        return ApiResponse::success(BlogPostResource::collection($posts), 'Blog posts fetched successfully.');
    }

    public function store(BlogPostRequest $request): JsonResponse
    {
        $this->authorize('create', BlogPost::class);

        $post = BlogPost::create($request->validated());

        return ApiResponse::success(new BlogPostResource($post), 'Blog post created successfully.', status: 201);
    }

    public function show(BlogPost $blogPost): JsonResponse
    {
        $this->authorize('view', $blogPost);

        return ApiResponse::success(new BlogPostResource($blogPost), 'Blog post fetched successfully.');
    }

    public function update(BlogPostRequest $request, BlogPost $blogPost): JsonResponse
    {
        $this->authorize('update', $blogPost);

        $blogPost->update($request->validated());

        return ApiResponse::success(new BlogPostResource($blogPost), 'Blog post updated successfully.');
    }

    public function destroy(BlogPost $blogPost): JsonResponse
    {
        $this->authorize('delete', $blogPost);

        $blogPost->delete();

        return ApiResponse::success(message: 'Blog post deleted successfully.');
    }
}
