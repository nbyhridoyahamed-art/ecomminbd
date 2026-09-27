<?php

namespace App\Support;

/**
 * Shared validation rule set for image uploads (spec section 16: JPEG,
 * PNG, WebP, AVIF) — one place so every upload endpoint enforces the same
 * type/size limits instead of duplicating the rule array.
 */
class ImageUploadRules
{
    public static function rules(int $maxKilobytes = 5120): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.$maxKilobytes];
    }
}
