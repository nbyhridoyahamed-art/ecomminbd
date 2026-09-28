<?php

namespace App\Support;

/**
 * The single source of truth for the product CSV column shape, shared by
 * ProductController::export() (writes these headers) and
 * ProductImportController (reads them back) — so a round-trip
 * export/edit/import always lines up, and a header a user reorders or
 * removes optional columns from still gets matched by name rather than
 * position.
 */
final class ProductCsv
{
    /**
     * Display header (as written to the export file) => internal field key.
     * "Type" is informational only — a variable product exports it, but
     * import never reads it, since import only ever creates simple
     * products or updates an existing product's own base fields without
     * touching its type or variants.
     */
    public const HEADERS = [
        'Type' => 'type',
        'SKU' => 'sku',
        'Name' => 'name',
        'Slug' => 'slug',
        'Category' => 'category',
        'Brand' => 'brand',
        'Status' => 'status',
        'Featured' => 'featured',
        'Price' => 'price',
        'Sale Price' => 'sale_price',
        'Cost Price' => 'cost_price',
        'Compare At Price' => 'compare_at_price',
        'Description' => 'description',
        'Short Description' => 'short_description',
        'Barcode' => 'barcode',
        'Weight' => 'weight',
        'Weight Unit' => 'weight_unit',
        'Track Stock' => 'track_stock',
        'Low Stock Threshold' => 'low_stock_threshold',
        'SEO Title' => 'seo_title',
        'SEO Description' => 'seo_description',
        'Focus Keyword' => 'focus_keyword',
    ];

    /**
     * Maps an uploaded CSV's header row to [internal_key => column_index],
     * matching header names case-insensitively and ignoring surrounding
     * whitespace. A header this app doesn't recognize is simply ignored,
     * rather than failing the whole import over an extra column.
     *
     * @return array<string, int>
     */
    public static function mapHeaderRow(array $headerRow): array
    {
        $lookup = [];
        foreach (self::HEADERS as $display => $key) {
            $lookup[strtolower($display)] = $key;
        }

        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim((string) $header));
            if (isset($lookup[$normalized])) {
                $map[$lookup[$normalized]] = $index;
            }
        }

        return $map;
    }
}
