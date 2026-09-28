<?php

namespace App\Http\Requests\BundleItem;

use App\Models\Product;
use App\Rules\VariantBelongsToProduct;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BundleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bundle = $this->route('product');

        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('store_id', $bundle->store_id)],
            'product_variant_id' => ['nullable', 'integer', new VariantBelongsToProduct],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bundle = $this->route('product');
            $componentId = $this->input('product_id');

            if ($componentId == $bundle->id) {
                $validator->errors()->add('product_id', 'A bundle cannot contain itself as a component.');

                return;
            }

            $component = Product::find($componentId);

            if (! $component) {
                return;
            }

            if ($component->type === 'bundle') {
                $validator->errors()->add('product_id', 'A bundle component cannot itself be a bundle.');
            }

            $isDuplicate = $bundle->bundleItems()
                ->where('component_product_id', $componentId)
                ->where('component_variant_id', $this->input('product_variant_id'))
                ->exists();

            if ($isDuplicate) {
                $validator->errors()->add('product_id', 'This product (or variant) is already a component of this bundle.');
            }
        });
    }
}
