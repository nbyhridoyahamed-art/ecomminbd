<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cod', 'bkash', 'nagad', 'rocket', 'card', 'bank_transfer'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
