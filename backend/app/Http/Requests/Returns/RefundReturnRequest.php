<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

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
            'note' => ['nullable', 'string'],
        ];
    }
}
