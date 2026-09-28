<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pageId = $this->route('page')?->id;
        $storeId = $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'slug')->where('store_id', $storeId)->ignore($pageId),
            ],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ];
    }
}
