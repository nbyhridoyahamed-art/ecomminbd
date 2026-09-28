<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->displayExcerpt(),
            'body' => $this->body,
            'featured_image_url' => $this->featured_image_url,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'name' => $this->category->name, 'slug' => $this->category->slug,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name, 'slug' => $tag->slug,
            ])),
            'reading_time_minutes' => $this->readingTimeMinutes(),
            'published_at' => $this->published_at,
            'seo' => $this->whenLoaded('seoMetadata', fn () => $this->seoMetadata ? [
                'title' => $this->seoMetadata->title,
                'description' => $this->seoMetadata->description,
                'canonical_url' => $this->seoMetadata->canonical_url,
                'robots' => $this->seoMetadata->robots,
                'og_title' => $this->seoMetadata->og_title,
                'og_description' => $this->seoMetadata->og_description,
                'og_image' => $this->seoMetadata->og_image,
                'schema_json' => $this->seoMetadata->schema_json,
            ] : null),
        ];
    }
}
