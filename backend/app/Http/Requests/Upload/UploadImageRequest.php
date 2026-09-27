<?php

namespace App\Http\Requests\Upload;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ImageUploadRules::rules(),
            'folder' => ['required', Rule::in(['categories', 'brands'])],
        ];
    }
}
