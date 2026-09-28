<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('brands', 'slug')->where('store_id', $storeId)->ignore($brandId),
            ],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:500'],
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
