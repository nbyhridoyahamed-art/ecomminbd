<?php

namespace App\Http\Requests\Inventory;

use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'exists:stores,id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', new VariantBelongsToProduct],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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
                $validator->errors()->add('items', 'Each product (or product variant) may only appear once per transfer.');
            }
        });
    }
}
