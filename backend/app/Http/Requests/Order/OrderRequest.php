<?php

namespace App\Http\Requests\Order;

use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->input('store_id');
        $customerId = $this->input('customer_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('store_id', $storeId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('store_id', $storeId)],
            'payment_method' => ['required', Rule::in(['cod', 'bkash', 'nagad', 'rocket', 'card', 'bank_transfer'])],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            // When present, overrides discount_amount with a server-resolved
            // coupon discount — see OrderController::resolveDiscount().
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],

            // Either an existing saved address, or a manually entered one.
            'customer_address_id' => ['nullable', Rule::exists('customer_addresses', 'id')->where('customer_id', $customerId)],
            'shipping_recipient_name' => ['required_without:customer_address_id', 'nullable', 'string', 'max:255'],
            'shipping_phone' => ['required_without:customer_address_id', 'nullable', 'string', 'max:20'],
            'shipping_address_line' => ['required_without:customer_address_id', 'nullable', 'string'],
            'shipping_bd_division_id' => ['nullable', 'exists:bd_divisions,id'],
            'shipping_bd_district_id' => ['nullable', 'exists:bd_districts,id'],
            'shipping_bd_upazila_id' => ['nullable', 'exists:bd_upazilas,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('store_id', $storeId)],
            'items.*.product_variant_id' => ['nullable', 'integer', new VariantBelongsToProduct],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
                $validator->errors()->add('items', 'Each product (or product variant) may only appear once per order.');
            }
        });
    }
}
