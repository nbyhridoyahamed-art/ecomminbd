<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('store_id', $storeId)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('store_id', $storeId)],

            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'slug')->where('store_id', $storeId)->ignore($productId),
            ],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('store_id', $storeId)->ignore($productId),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            // 'digital'/'service'/'bundle'/'combo' are still reserved, not
            // functional — see DEVELOPMENT_ROADMAP.md's Phase 5 scope notes.
            'type' => ['nullable', Rule::in(['simple', 'variable'])],

            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:1000'],

            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],

            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', Rule::in(['kg', 'g', 'lb'])],

            'track_stock' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],

            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'featured' => ['nullable', 'boolean'],

            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
        ];
    }
}
