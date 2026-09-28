<?php

namespace App\Http\Requests\HomepageBlock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepageBlockReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->user()->current_store_id;

        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', Rule::exists('homepage_blocks', 'id')->where('store_id', $storeId)],
        ];
    }
}
