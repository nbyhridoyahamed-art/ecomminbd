<?php

namespace App\Http\Requests\SavedSection;

use App\Support\HomepageBlockTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavedSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = (int) $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(HomepageBlockTypes::ALL)],
            'settings' => ['required', 'array'],
            ...HomepageBlockTypes::settingsRules($this->input('type'), $storeId),
            ...HomepageBlockTypes::styleRules(),
        ];
    }
}
