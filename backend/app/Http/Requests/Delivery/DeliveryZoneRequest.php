<?php

namespace App\Http\Requests\Delivery;

use App\Models\DeliveryZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'bd_division_id' => ['nullable', 'exists:bd_divisions,id'],
            'bd_district_id' => ['nullable', 'exists:bd_districts,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.min_order_subtotal' => ['required', 'numeric', 'min:0'],
            'rates.*.rate_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $divisionId = $this->input('bd_division_id') ?: null;
            $districtId = $this->input('bd_district_id') ?: null;

            if ($districtId !== null && $divisionId === null) {
                $validator->errors()->add('bd_district_id', 'A district cannot be set without a division.');
            }

            $zoneId = $this->route('delivery_zone')?->id;
            $duplicate = DeliveryZone::query()
                ->where('store_id', $this->input('store_id'))
                ->where('bd_division_id', $divisionId)
                ->where('bd_district_id', $districtId)
                ->when($zoneId, fn ($query) => $query->where('id', '!=', $zoneId))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('bd_division_id', $divisionId === null
                    ? 'A default delivery zone already exists for this store.'
                    : 'A delivery zone already covers this exact location.');
            }

            $subtotals = array_map(
                fn (array $rate) => (float) ($rate['min_order_subtotal'] ?? -1),
                $this->input('rates', []),
            );

            if (count($subtotals) !== count(array_unique($subtotals))) {
                $validator->errors()->add('rates', 'Each rate tier must start at a different minimum order amount.');
            }

            if (! in_array(0.0, $subtotals, true)) {
                $validator->errors()->add('rates', 'One rate must start at a minimum order amount of 0, so every order has a rate.');
            }
        });
    }
}
