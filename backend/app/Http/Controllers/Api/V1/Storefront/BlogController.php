<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Resources\Storefront\BlogCategoryResource;
use App\Http\Resources\Storefront\BlogPostDetailResource;
use App\Http\Resources\Storefront\BlogPostResource;
use App\Http\Resources\Storefront\BlogTagResource;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class BlogController extends StorefrontController
{
    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $perPage = min((int) $request->integer('per_page', 10), 60);

        $posts = BlogPost::query()
            ->where('store_id', $store->id)
            ->published()
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return ApiResponse::success(BlogPostResource::collection($posts), 'Blog posts fetched successfully.', $this->paginationMeta($posts));
    }

    public function show(string $slug): JsonResponse
    {
        $store = $this->currentStore();

        $post = BlogPost::query()
            ->where('store_id', $store->id)
            ->published()
            ->where('slug', $slug)
            ->with(['category', 'tags', 'seoMetadata'])
            ->firstOrFail();

        $related = $post->blog_category_id
            ? BlogPost::query()
                ->where('store_id', $store->id)
                ->published()
                ->where('blog_category_id', $post->blog_category_id)
                ->where('id', '!=', $post->id)
                ->with(['category', 'tags'])
                ->orderByDesc('published_at')
                ->limit(3)
                ->get()
            : collect();

        return ApiResponse::success([
            'post' => new BlogPostDetailResource($post),
            'related_posts' => BlogPostResource::collection($related),
        ], 'Blog post fetched successfully.');
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $store = $this->currentStore();
        $perPage = min((int) $request->integer('per_page', 10), 60);

        $category = BlogCategory::query()->where('store_id', $store->id)->where('slug', $slug)->with('seoMetadata')->firstOrFail();

        $posts = BlogPost::query()
            ->where('store_id', $store->id)
            ->published()
            ->where('blog_category_id', $category->id)
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return ApiResponse::success(
            ['category' => new BlogCategoryResource($category), 'posts' => BlogPostResource::collection($posts)],
            'Blog category fetched successfully.',
            $this->paginationMeta($posts),
        );
    }

    public function tag(Request $request, string $slug): JsonResponse
    {
        $store = $this->currentStore();
        $perPage = min((int) $request->integer('per_page', 10), 60);

        $tag = BlogTag::query()->where('store_id', $store->id)->where('slug', $slug)->with('seoMetadata')->firstOrFail();

        $posts = $tag->posts()
            ->published()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return ApiResponse::success(
            ['tag' => new BlogTagResource($tag), 'posts' => BlogPostResource::collection($posts)],
            'Blog tag fetched successfully.',
            $this->paginationMeta($posts),
        );
    }

    /** Hand-built RSS 2.0 — no third-party feed package for twenty-odd lines of XML. */
    public function rss(): Response
    {
        $store = $this->currentStore();

        $posts = BlogPost::query()
            ->where('store_id', $store->id)
            ->published()
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('blog.rss', ['store' => $store, 'posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
