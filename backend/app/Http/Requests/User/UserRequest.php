<?php

namespace App\Http\Requests\User;

use App\Rules\BdPhone;
use App\Support\BdPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;
        $isCreating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', new BdPhone, Rule::unique('users', 'phone')->ignore($userId)],
            'password' => [$isCreating ? 'required' : 'nullable', Password::min(8)],
            'current_store_id' => ['nullable', 'exists:stores,id'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
