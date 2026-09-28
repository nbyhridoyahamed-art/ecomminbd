<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $categoryId = $this->route('category')?->id;
            $parentId = $this->input('parent_id');

            if (! $categoryId || ! $parentId) {
                return;
            }

            // Walk up the proposed parent's ancestor chain — if the category
            // being edited appears anywhere in it, this update would create
            // a cycle (a category that is its own ancestor), which would
            // infinite-loop any tree traversal.
            $ancestorId = $parentId;
            $depth = 0;

            while ($ancestorId !== null && $depth < 50) {
                if ($ancestorId === $categoryId) {
                    $validator->errors()->add('parent_id', 'A category cannot be a descendant of itself.');

                    return;
                }

                $ancestorId = Category::query()->whereKey($ancestorId)->value('parent_id');
                $depth++;
            }
        });
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('store_id', $storeId),
                Rule::notIn([$categoryId]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'slug')->where('store_id', $storeId)->ignore($categoryId),
            ],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],

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
