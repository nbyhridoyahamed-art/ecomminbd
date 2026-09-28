<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductImportRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\Money;
use App\Support\ProductCsv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Bulk-creates or updates *simple* products from a CSV (see ProductCsv for
 * the shared column shape with export). A row's Type column, if present, is
 * never read — import can't create a variable product or touch an existing
 * one's variants, only its own base fields (name, price, etc.), so a
 * variable product stays exactly as variant-managed as it was before.
 */
class ProductImportController extends Controller
{
    public function store(ProductImportRequest $request): JsonResponse
    {
        if (! $request->user()->can('products.create')) {
            throw new AuthorizationException;
        }

        $storeId = $request->integer('store_id');
        $handle = fopen($request->file('file')->getRealPath(), 'rb');

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return ApiResponse::error('The file is empty.', [], 422);
        }
        // Strip a UTF-8 BOM Excel commonly prepends to the first cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $columns = ProductCsv::mapHeaderRow($header);
        if (! isset($columns['sku'], $columns['name'], $columns['price'])) {
            fclose($handle);

            return ApiResponse::error('The file must include at least SKU, Name, and Price columns.', [], 422);
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $cell = function (string $key) use ($columns, $row): ?string {
                if (! isset($columns[$key], $row[$columns[$key]])) {
                    return null;
                }
                $value = trim((string) $row[$columns[$key]]);

                return $value === '' ? null : $value;
            };

            $fields = [
                'sku' => $cell('sku'),
                'name' => $cell('name'),
                'slug' => $cell('slug'),
                'category' => $cell('category'),
                'brand' => $cell('brand'),
                'status' => $cell('status'),
                'featured' => $cell('featured'),
                'price' => $cell('price'),
                'sale_price' => $cell('sale_price'),
                'cost_price' => $cell('cost_price'),
                'compare_at_price' => $cell('compare_at_price'),
                'description' => $cell('description'),
                'short_description' => $cell('short_description'),
                'barcode' => $cell('barcode'),
                'weight' => $cell('weight'),
                'weight_unit' => $cell('weight_unit'),
                'track_stock' => $cell('track_stock'),
                'low_stock_threshold' => $cell('low_stock_threshold'),
            ];

            $validator = Validator::make($fields, [
                'sku' => ['required', 'string', 'max:100'],
                'name' => ['required', 'string', 'max:255'],
                'price' => ['required', 'numeric', 'min:0'],
                'sale_price' => ['nullable', 'numeric', 'min:0'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'compare_at_price' => ['nullable', 'numeric', 'min:0'],
                'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
                'weight' => ['nullable', 'numeric', 'min:0'],
                'weight_unit' => ['nullable', Rule::in(['kg', 'g', 'lb'])],
                'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                DB::transaction(function () use ($fields, $storeId, $request, &$created, &$updated) {
                    $product = Product::query()->where('store_id', $storeId)->where('sku', $fields['sku'])->first();
                    $currency = $product->currency_code ?? 'BDT';

                    $payload = [
                        'category_id' => $fields['category'] ? $this->resolveByName(Category::class, $storeId, $fields['category']) : null,
                        'brand_id' => $fields['brand'] ? $this->resolveByName(Brand::class, $storeId, $fields['brand']) : null,
                        'name' => $fields['name'],
                        'barcode' => $fields['barcode'],
                        'description' => $fields['description'],
                        'short_description' => $fields['short_description'],
                        'price_amount' => Money::fromDecimal($fields['price'], $currency)->amountMinor,
                        'sale_price_amount' => Money::fromDecimal($fields['sale_price'], $currency)?->amountMinor,
                        'cost_price_amount' => Money::fromDecimal($fields['cost_price'], $currency)?->amountMinor,
                        'compare_at_price_amount' => Money::fromDecimal($fields['compare_at_price'], $currency)?->amountMinor,
                        'weight' => $fields['weight'],
                        'weight_unit' => $fields['weight_unit'],
                        'low_stock_threshold' => $fields['low_stock_threshold'],
                        'updated_by' => $request->user()->id,
                    ];

                    // status/featured/track_stock are non-nullable columns with
                    // a real "current state" — an update row that leaves the
                    // cell blank keeps the product's existing value rather than
                    // resetting it to a hardcoded default (every other column
                    // above is nullable, so blank there means "clear it" — see
                    // the CSV import scope note in the docs for why these two
                    // rules differ).
                    if ($fields['status']) {
                        $payload['status'] = $fields['status'];
                    }
                    if ($fields['track_stock'] !== null) {
                        $payload['track_stock'] = $this->parseBool($fields['track_stock']);
                    }
                    if ($fields['featured'] !== null) {
                        $payload['featured'] = $this->parseBool($fields['featured']);
                    }

                    if ($product) {
                        if (($payload['status'] ?? null) === 'active' && $product->published_at === null) {
                            $payload['published_at'] = now();
                        }
                        $product->update($payload);
                        $updated++;

                        return;
                    }

                    $payload['store_id'] = $storeId;
                    $payload['sku'] = $fields['sku'];
                    $payload['slug'] = $this->uniqueSlug(Product::class, $storeId, $fields['slug'] ?? $fields['name']);
                    $payload['type'] = 'simple';
                    $payload['status'] ??= 'draft';
                    $payload['track_stock'] ??= true;
                    $payload['featured'] ??= false;
                    $payload['created_by'] = $request->user()->id;
                    if ($payload['status'] === 'active') {
                        $payload['published_at'] = now();
                    }
                    Product::create($payload);
                    $created++;
                });
            } catch (Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Could not save this row: '.$exception->getMessage()];
            }
        }

        fclose($handle);

        return ApiResponse::success([
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($errors),
            'errors' => $errors,
        ], 'Import completed.');
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }

    /** @param  class-string<Category|Brand>  $modelClass */
    private function resolveByName(string $modelClass, int $storeId, string $name): int
    {
        $existing = $modelClass::query()
            ->where('store_id', $storeId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return $modelClass::create([
            'store_id' => $storeId,
            'name' => $name,
            'slug' => $this->uniqueSlug($modelClass, $storeId, $name),
            'status' => 'active',
        ])->id;
    }

    /** @param  class-string<Product|Category|Brand>  $modelClass */
    private function uniqueSlug(string $modelClass, int $storeId, string $base): string
    {
        $slug = Str::slug($base) ?: 'item';
        $candidate = $slug;
        $suffix = 2;

        while ($modelClass::query()->where('store_id', $storeId)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
