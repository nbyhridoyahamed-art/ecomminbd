<?php

namespace App\Http\Requests\ProductAttribute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attributeId = $this->route('productAttribute')?->id;
        $valueId = $this->route('value')?->id;

        return [
            'value' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('product_attribute_values', 'slug')->where('product_attribute_id', $attributeId)->ignore($valueId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
