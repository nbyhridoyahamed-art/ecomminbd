<?php

namespace App\Http\Requests\HomepageBlock;

use Illuminate\Foundation\Http\FormRequest;

class HomepageBlockScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
