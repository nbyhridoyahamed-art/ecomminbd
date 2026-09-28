<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks a bundle product wherever a direct stock operation is being
 * requested — stock adjustments, transfers, and purchase orders all touch
 * a product's own stock_levels row, which a bundle never has (its stock is
 * derived from its components — see BundleExpander). Orders are the one
 * exception: a bundle is meant to be ordered, so OrderRequest doesn't use
 * this rule.
 */
class ProductIsNotBundle implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Product::find($value)?->type === 'bundle') {
            $fail('A bundle cannot be used here directly — use its component products instead.');
        }
    }
}
