<?php

namespace App\Http\Requests\Auth;

use App\Rules\BdPhone;
use App\Support\BdPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', new BdPhone, 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
