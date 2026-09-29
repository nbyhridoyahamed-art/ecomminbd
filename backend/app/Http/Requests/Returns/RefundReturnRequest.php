<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional — defaults to the covered items' order-time price
            // (see ReturnController::suggestedRefundAmount()) when omitted.
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            // original_payment (default, Wave 1's only behavior) sends the
            // money back outside the system; store_credit instead issues a
            // customer_store_credits ledger entry for the same amount.
            'refund_method' => ['nullable', Rule::in(['original_payment', 'store_credit'])],
            'note' => ['nullable', 'string'],
        ];
    }
}
