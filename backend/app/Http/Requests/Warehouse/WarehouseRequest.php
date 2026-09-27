<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')->where('store_id', $storeId)->ignore($warehouseId),
            ],
            'type' => ['required', Rule::in(['main', 'branch', 'pickup_point', 'temporary'])],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_line' => ['nullable', 'string'],
            'bd_division_id' => ['nullable', 'exists:bd_divisions,id'],
            'bd_district_id' => ['nullable', 'exists:bd_districts,id'],
            'bd_upazila_id' => ['nullable', 'exists:bd_upazilas,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
