<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Codes are matched exactly (see CouponResolver::resolve()) — normalizing
        // to uppercase here means a customer typing "save10" at checkout still
        // matches a coupon created as "SAVE10", without a case-insensitive query.
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper(trim($this->string('code')))]);
        }
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('coupons', 'code')->where('store_id', $storeId)->ignore($couponId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'percentage_value' => ['required_if:discount_type,percentage', 'nullable', 'integer', 'min:1', 'max:100'],
            'fixed_amount' => ['required_if:discount_type,fixed', 'nullable', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
