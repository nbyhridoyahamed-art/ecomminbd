<?php

namespace App\Http\Requests\Returns;

use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Rules\VariantBelongsToProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $orderId = $order?->id;
        $storeId = $order?->store_id;

        return [
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', Rule::exists('order_items', 'id')->where('order_id', $orderId)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.restock' => ['nullable', 'boolean'],
            // What the customer wants instead, decided up front — acted on
            // (stock moved via a replacement order) at receive() time, same
            // as restock. Independent of restock: the old item can be both
            // restocked and exchanged for something else.
            'items.*.exchange_product_id' => ['nullable', Rule::exists('products', 'id')->where('store_id', $storeId)],
            'items.*.exchange_product_variant_id' => ['nullable', 'integer', new VariantBelongsToProduct],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);

            $orderItemIds = array_column($items, 'order_item_id');
            if (count($orderItemIds) !== count(array_unique($orderItemIds))) {
                $validator->errors()->add('items', 'Each order item may only appear once per return request.');

                return;
            }

            foreach ($items as $index => $item) {
                if (! isset($item['order_item_id'], $item['quantity'])) {
                    continue;
                }

                $orderItem = OrderItem::find($item['order_item_id']);
                if (! $orderItem) {
                    continue;
                }

                // Non-rejected returns already requested against this line —
                // a rejected return frees its quantity back up for a fresh request.
                $alreadyReturned = ReturnItem::query()
                    ->where('order_item_id', $orderItem->id)
                    ->whereHas('orderReturn', fn ($query) => $query->where('status', '!=', 'rejected'))
                    ->sum('quantity');

                $remaining = $orderItem->quantity - $alreadyReturned;

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
