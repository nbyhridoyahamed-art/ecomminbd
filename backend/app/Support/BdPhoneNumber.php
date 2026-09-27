<?php

namespace App\Support;

/**
 * Normalizes Bangladesh mobile numbers to a single canonical form
 * (01XXXXXXXXX) so the same number typed as 01712345678, +8801712345678,
 * or 8801712345678 always collides on the same customer/user record.
 */
class BdPhoneNumber
{
    public static function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 3);
        }

        if (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return self::isValid($digits) ? $digits : null;
    }

    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', $normalized);
    }
}
