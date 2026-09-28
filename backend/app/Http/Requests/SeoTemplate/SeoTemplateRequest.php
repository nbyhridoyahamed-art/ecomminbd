<?php

namespace App\Http\Requests\SeoTemplate;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeoTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('seo_template')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'entity_type' => [
                'required',
                Rule::in([
                    Product::class, Category::class, Brand::class, Page::class,
                    BlogPost::class, BlogCategory::class, BlogTag::class,
                ]),
                Rule::unique('seo_templates', 'entity_type')->where('store_id', $storeId)->ignore($templateId),
            ],
            'title_template' => ['nullable', 'string', 'max:255'],
            'description_template' => ['nullable', 'string', 'max:500'],
        ];
    }
}
