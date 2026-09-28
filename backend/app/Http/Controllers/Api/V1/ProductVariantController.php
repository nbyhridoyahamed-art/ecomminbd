<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariant\GenerateVariantsRequest;
use App\Http\Requests\ProductVariant\ProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductVariantController extends Controller
{
    private const RELATIONS = ['attributeValues.attribute', 'product'];

    /**
     * Creates the cartesian product of the given attribute values as
     * variants — e.g. Color:{Red,Blue} x Size:{S,M} generates 4 variants.
     * Combinations that already exist as a variant (matched by their exact
     * set of attribute values) are skipped, so re-running this after adding
     * one new value only creates the new combinations, never duplicates.
     */
    public function generate(GenerateVariantsRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        if ($product->type !== 'variable') {
            return ApiResponse::error('Only a variable product can have variants.', [], 422);
        }

        $values = ProductAttributeValue::with('attribute')
            ->whereIn('id', $request->validated('attribute_value_ids'))
            ->get();

        if ($values->contains(fn ($value) => $value->attribute->store_id !== $product->store_id)) {
            return ApiResponse::error('One or more attribute values do not belong to this store.', [], 422);
        }

        $valueGroups = $values->groupBy('product_attribute_id')->values();

        $combinations = [[]];
        foreach ($valueGroups as $group) {
            $next = [];
            foreach ($combinations as $combo) {
                foreach ($group as $value) {
                    $next[] = [...$combo, $value];
                }
            }
            $combinations = $next;
        }

        $existingSignatures = $product->variants()
            ->with('attributeValues')
            ->get()
            ->map(fn (ProductVariant $variant) => $this->signature($variant->attributeValues->pluck('id')->all()))
            ->all();

        DB::transaction(function () use ($combinations, $existingSignatures, $product) {
            foreach ($combinations as $combo) {
                $valueIds = array_map(fn ($value) => $value->id, $combo);

                if (in_array($this->signature($valueIds), $existingSignatures, true)) {
                    continue;
                }

                $slugs = array_map(fn ($value) => Str::upper(Str::slug($value->value)), $combo);

                $variant = ProductVariant::create([
                    'store_id' => $product->store_id,
                    'product_id' => $product->id,
                    'sku' => $product->sku.'-'.implode('-', $slugs),
                    'status' => 'active',
                ]);

                $variant->attributeValues()->attach($valueIds);
            }
        });

        return ApiResponse::success(
            ProductVariantResource::collection($product->variants()->with(self::RELATIONS)->get()),
            'Variants generated successfully.',
            status: 201,
        );
    }

    public function update(ProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        if ($variant->product_id !== $product->id) {
            return ApiResponse::error('This variant does not belong to this product.', [], 404);
        }

        $data = $request->validated();
        $currency = $product->currency_code;

        $data['price_amount'] = Money::fromDecimal($data['price'] ?? null, $currency)?->amountMinor;
        $data['sale_price_amount'] = Money::fromDecimal($data['sale_price'] ?? null, $currency)?->amountMinor;
        $data['cost_price_amount'] = Money::fromDecimal($data['cost_price'] ?? null, $currency)?->amountMinor;
        unset($data['price'], $data['sale_price'], $data['cost_price']);

        $variant->update($data);

        return ApiResponse::success(new ProductVariantResource($variant->load(self::RELATIONS)), 'Variant updated successfully.');
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);

        if ($variant->product_id !== $product->id) {
            return ApiResponse::error('This variant does not belong to this product.', [], 404);
        }

        $variant->delete();

        return ApiResponse::success(message: 'Variant deleted successfully.');
    }

    /** A comparable signature for a set of attribute-value ids, order-independent. */
    private function signature(array $valueIds): string
    {
        sort($valueIds);

        return implode(',', $valueIds);
    }
}
