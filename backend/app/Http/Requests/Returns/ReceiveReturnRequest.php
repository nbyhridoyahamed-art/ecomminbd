<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $returnId = $this->route('orderReturn')?->id;

        return [
            // Optional per-item restock overrides — the condition of a
            // returned item is only really known once it's physically back,
            // so this is where staff confirms/corrects the restock=true
            // default each item was requested with.
            'items' => ['nullable', 'array'],
            'items.*.return_item_id' => ['required', Rule::exists('return_items', 'id')->where('return_id', $returnId)],
            'items.*.restock' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
        ];
    }
}
