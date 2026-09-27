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
        ];
    }
}
