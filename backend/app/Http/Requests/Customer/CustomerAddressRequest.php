<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string'],
            'bd_division_id' => ['nullable', 'exists:bd_divisions,id'],
            'bd_district_id' => ['nullable', 'exists:bd_districts,id'],
            'bd_upazila_id' => ['nullable', 'exists:bd_upazilas,id'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
