<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * No phone field — phone is the login identifier and changing it would
 * need its own re-verification flow (OTP) that doesn't exist yet; a
 * customer who needs it corrected contacts support, same as a staff user
 * can't self-service their own email today either.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
