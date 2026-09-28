<?php

namespace App\Http\Requests\Purchasing;

use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('store_id', $storeId)],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('store_id', $storeId)],
            'items.*.product_variant_id' => ['nullable', 'integer', new VariantBelongsToProduct],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = array_map(
                fn (array $item) => ($item['product_id'] ?? '').':'.($item['product_variant_id'] ?? ''),
                $this->input('items', []),
            );
            if (count($lines) !== count(array_unique($lines))) {
                $validator->errors()->add('items', 'Each product (or product variant) may only appear once per purchase order.');
            }
        });
    }
}
