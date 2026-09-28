<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'featured_image_url' => $this->featured_image_url,
            'blog_category_id' => $this->blog_category_id,
            'category' => new BlogCategoryResource($this->whenLoaded('category')),
            'tags' => BlogTagResource::collection($this->whenLoaded('tags')),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'reading_time_minutes' => $this->readingTimeMinutes(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
