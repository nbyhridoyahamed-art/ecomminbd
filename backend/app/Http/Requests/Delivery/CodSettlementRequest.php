<?php

namespace App\Http\Requests\Delivery;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CodSettlementRequest extends FormRequest
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
            'courier_id' => ['required', Rule::exists('couriers', 'id')->where('store_id', $storeId)],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $courierId = $this->input('courier_id');
            $shipmentIds = $this->input('shipment_ids', []);

            $eligibleCount = Shipment::query()
                ->whereIn('id', $shipmentIds)
                ->where('courier_id', $courierId)
                ->where('status', 'delivered')
                ->where('cod_settled', false)
                ->whereHas('order', fn ($query) => $query->where('payment_method', 'cod'))
                ->count();

            if ($eligibleCount !== count($shipmentIds)) {
                $validator->errors()->add(
                    'shipment_ids',
                    'One or more shipments are not eligible for settlement (must belong to this courier, be delivered, COD, and not already settled).',
                );
            }
        });
    }
}
