<?php

namespace App\Http\Requests\Catalog;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ImageUploadRules::rules(),
            'alt_text' => ['nullable', 'string', 'max:255'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ];
    }
}
