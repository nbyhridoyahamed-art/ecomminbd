<?php

namespace App\Http\Requests\HomepageBlock;

use App\Support\HomepageBlockTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomepageBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $block = $this->route('homepage_block');
        $styleRules = HomepageBlockTypes::styleRules();

        // A block's type is fixed at creation — its settings shape is
        // type-specific, so "changing type" is really "make a new block."
        // update() only ever reads `settings`/styles/etc from validated data.
        if ($block) {
            return [
                'settings' => ['required', 'array'],
                ...HomepageBlockTypes::settingsRules($block->type, $block->store_id),
                ...$styleRules,
            ];
        }

        $storeId = (int) $this->input('store_id');

        return [
            'store_id' => ['required', 'exists:stores,id'],
            'type' => ['required', 'string', Rule::in(HomepageBlockTypes::ALL)],
            'settings' => ['required', 'array'],
            ...HomepageBlockTypes::settingsRules($this->input('type'), $storeId),
            ...$styleRules,
        ];
    }
}
