<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The card shape for the blog index, category, and tag archive pages. */
class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->displayExcerpt(),
            'featured_image_url' => $this->featured_image_url,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'name' => $this->category->name, 'slug' => $this->category->slug,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'name' => $tag->name, 'slug' => $tag->slug,
            ])),
            'reading_time_minutes' => $this->readingTimeMinutes(),
            'published_at' => $this->published_at,
        ];
    }
}
