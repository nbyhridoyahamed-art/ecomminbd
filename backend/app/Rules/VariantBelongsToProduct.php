<?php

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a submitted product_variant_id belongs to the product_id
 * given alongside it in the same request — e.g. items.2.product_variant_id
 * must belong to items.2.product_id, or a flat product_variant_id must
 * belong to a flat product_id. Reused across every line-item form that
 * accepts an optional variant (orders, purchase orders, stock
 * adjustments/transfers).
 */
class VariantBelongsToProduct implements DataAwareRule, ValidationRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $productAttribute = preg_replace('/product_variant_id$/', 'product_id', $attribute);
        $productId = data_get($this->data, $productAttribute);

        $exists = ProductVariant::query()->where('id', $value)->where('product_id', $productId)->exists();

        if (! $exists) {
            $fail('The selected variant does not belong to the selected product.');
        }
    }
}
