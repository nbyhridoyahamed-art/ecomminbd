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
            'featured_image_url' => ['nullable', 'string', 'max:2048'],
            'published_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
