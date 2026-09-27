<?php

namespace App\Rules;

use App\Support\BdPhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BdPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || BdPhoneNumber::normalize($value) === null) {
            $fail('The :attribute must be a valid Bangladesh mobile number (e.g. 01712345678).');
        }
    }
}
