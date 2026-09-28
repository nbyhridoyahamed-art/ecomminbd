<?php

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturnItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $purchaseOrderId = $this->route('purchaseOrder')?->id;

        return [
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $purchaseOrderId)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);

            $itemIds = array_column($items, 'purchase_order_item_id');
            if (count($itemIds) !== count(array_unique($itemIds))) {
                $validator->errors()->add('items', 'Each purchase order item may only appear once per return request.');

                return;
            }

            foreach ($items as $index => $item) {
                if (! isset($item['purchase_order_item_id'], $item['quantity'])) {
                    continue;
                }

                $orderItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                if (! $orderItem) {
                    continue;
                }

                // Non-rejected returns already requested against this line —
                // a rejected return frees its quantity back up for a fresh
                // request. Bounded by what was actually received, not what
                // was ordered — goods still in transit can't be sent back.
                $alreadyReturned = PurchaseReturnItem::query()
                    ->where('purchase_order_item_id', $orderItem->id)
                    ->whereHas('purchaseReturn', fn ($query) => $query->where('status', '!=', 'rejected'))
                    ->sum('quantity');

                $remaining = $orderItem->quantity_received - $alreadyReturned;

                if ($item['quantity'] > $remaining) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "Only {$remaining} unit(s) of \"{$orderItem->product->name}\" remain eligible for return.",
                    );
                }
            }
        });
    }
}
