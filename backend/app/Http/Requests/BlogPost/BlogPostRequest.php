<?php

namespace App\Http\Requests\BlogPost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $postId = $this->route('blog_post')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('blog_posts', 'slug')->where('store_id', $storeId)->ignore($postId),
            ],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'featured_image_url' => ['nullable', 'string', 'max:2048'],
            'blog_category_id' => ['nullable', Rule::exists('blog_categories', 'id')->where('store_id', $storeId)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [Rule::exists('blog_tags', 'id')->where('store_id', $storeId)],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],

            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:255'],
            'seo.description' => ['nullable', 'string', 'max:500'],
            'seo.focus_keyword' => ['nullable', 'string', 'max:255'],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image' => ['nullable', 'string', 'max:2048'],
            'seo.twitter_title' => ['nullable', 'string', 'max:255'],
            'seo.twitter_description' => ['nullable', 'string', 'max:500'],
            'seo.twitter_image' => ['nullable', 'string', 'max:2048'],
            'seo.canonical_url' => ['nullable', 'string', 'max:2048'],
            'seo.robots' => ['nullable', 'string', 'max:255'],
            'seo.schema_json' => ['nullable', 'array'],
        ];
    }
}
