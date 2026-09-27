<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderId = $this->route('purchaseOrder')?->id;

        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => [
                'required',
                Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $orderId),
            ],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
        ];
    }
}
