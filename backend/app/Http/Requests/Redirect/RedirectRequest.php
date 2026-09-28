<?php

namespace App\Http\Requests\Redirect;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $redirectId = $this->route('redirect')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'from_path' => [
                'required', 'string', 'max:255', 'regex:/^\//',
                Rule::unique('redirects', 'from_path')->where('store_id', $storeId)->ignore($redirectId),
            ],
            'to_path' => ['required', 'string', 'max:255'],
            'status_code' => ['nullable', Rule::in([301, 302, 307, 308])],
        ];
    }
}
