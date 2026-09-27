<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->route('order')?->store_id;

        return [
            'courier_id' => ['required', Rule::exists('couriers', 'id')->where('store_id', $storeId)],
            'tracking_number' => ['required', 'string', 'max:100'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
