<?php

namespace App\Http\Requests\Account;

use App\Rules\BdPhone;
use App\Support\BdPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Deliberately no store_id (single active store, same resolution as the
 * rest of the storefront) and no unique:customers,phone rule — an
 * existing phone is expected and handled deliberately in
 * AuthController::register() (claims an unclaimed guest-checkout
 * Customer, rejects an already-claimed one), not a plain uniqueness
 * failure.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => BdPhoneNumber::normalize($this->string('phone')) ?? $this->input('phone')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new BdPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
