<?php

namespace App\Http\Requests\Product;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
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
        ];
    }
}
