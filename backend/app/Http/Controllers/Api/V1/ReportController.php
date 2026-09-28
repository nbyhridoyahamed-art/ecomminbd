<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 18 Wave 1 — activates the `reports.view` permission the RBAC
 * seeder has carried since Phase 3 but no controller checked yet. Every
 * report here is a pure read-side aggregate over already-existing tables
 * (orders/order_items/products/stock_levels) — no materialized/scheduled
 * aggregate table, same "compute it fresh" reasoning as
 * DashboardController, until a real data volume makes that too slow (see
 * DATABASE_DESIGN.md section 2's Reporting/Analytics bullet).
 */
class ReportController extends Controller
{
    public function salesReport(Request $request): JsonResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $data = $this->resolveFilters($request);
        $currencyCode = Store::find($data['store_id'])?->currency?->code ?? 'BDT';

        $dayRows = $this->dailySalesRows($data);
        $byPeriod = $this->foldByGranularity($dayRows, $data['granularity'], $currencyCode);

        $ordersCount = (int) $dayRows->sum('orders_count');
        $revenueMinor = (int) $dayRows->sum('revenue_minor');
        $averageMinor = $ordersCount > 0 ? intdiv($revenueMinor, $ordersCount) : 0;

        $byPaymentMethod = $this->paymentMethodQuery($data)->get()->map(fn ($row) => [
            'payment_method' => $row->payment_method,
            'orders_count' => (int) $row->orders_count,
            'revenue_amount' => (new Money((int) $row->revenue_minor, $currencyCode))->toDecimal(),
        ]);

        $byCourier = $this->courierQuery($data)->get()->map(fn ($row) => [
            'courier_id' => (int) $row->courier_id,
            'courier_name' => $row->courier_name,
            'orders_count' => (int) $row->orders_count,
            'revenue_amount' => (new Money((int) $row->revenue_minor, $currencyCode))->toDecimal(),
        ]);

        return ApiResponse::success([
            'totals' => [
                'revenue_amount' => (new Money($revenueMinor, $currencyCode))->toDecimal(),
                'orders_count' => $ordersCount,
                'average_order_value' => (new Money($averageMinor, $currencyCode))->toDecimal(),
            ],
            'by_period' => $byPeriod,
            'by_payment_method' => $byPaymentMethod,
            'by_courier' => $byCourier,
        ], 'Sales report fetched successfully.');
    }

    public function salesReportExport(Request $request): StreamedResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $data = $this->resolveFilters($request);
        $currencyCode = Store::find($data['store_id'])?->currency?->code ?? 'BDT';
        $byPeriod = $this->foldByGranularity($this->dailySalesRows($data), $data['granularity'], $currencyCode);

        return response()->streamDownload(function () use ($byPeriod) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Orders', 'Revenue']);
            foreach ($byPeriod as $row) {
                fputcsv($handle, [$row['date'], $row['orders_count'], $row['revenue_amount']]);
            }
            fclose($handle);
        }, 'sales-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Rolls a variable product's variant sales up into one row — a merchandising view of "how did this product do," not a per-variant breakdown (that lives on the product's own Variants tab). */
    public function productPerformance(Request $request): JsonResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $data = $this->resolveFilters($request);
        $currencyCode = Store::find($data['store_id'])?->currency?->code ?? 'BDT';
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $rows = $this->productPerformanceQuery($data)->paginate($perPage);

        return ApiResponse::success(
            collect($rows->items())->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'units_sold' => (int) $row->units_sold,
                'revenue_amount' => (new Money((int) $row->revenue_minor, $currencyCode))->toDecimal(),
            ]),
            'Product performance fetched successfully.',
            [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        );
    }

    public function productPerformanceExport(Request $request): StreamedResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $data = $this->resolveFilters($request);
        $currencyCode = Store::find($data['store_id'])?->currency?->code ?? 'BDT';
        $rows = $this->productPerformanceQuery($data)->get();

        return response()->streamDownload(function () use ($rows, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Product', 'SKU', 'Units Sold', 'Revenue']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->sku,
                    $row->units_sold,
                    (new Money((int) $row->revenue_minor, $currencyCode))->toDecimal(),
                ]);
            }
            fclose($handle);
        }, 'product-performance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Cross-warehouse, unlike StockLevelController::index() — a low-stock product's name should surface regardless of which warehouse is short, and a variable product's variants are summed together, same convention as the Stock Levels list. */
    public function lowStock(Request $request): JsonResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $rows = $this->lowStockQuery($request->integer('store_id'))->paginate($perPage);

        return ApiResponse::success(
            collect($rows->items())->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'total_quantity' => (int) $row->total_quantity,
                'total_reserved' => (int) $row->total_reserved,
                'total_available' => (int) $row->total_quantity - (int) $row->total_reserved,
                'low_stock_threshold' => (int) $row->low_stock_threshold,
            ]),
            'Low stock report fetched successfully.',
            [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        );
    }

    public function lowStockExport(Request $request): StreamedResponse
    {
        if (! $request->user()->can('reports.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);
        $rows = $this->lowStockQuery($request->integer('store_id'))->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Product', 'SKU', 'On Hand', 'Reserved', 'Available', 'Threshold']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->sku,
                    $row->total_quantity,
                    $row->total_reserved,
                    (int) $row->total_quantity - (int) $row->total_reserved,
                    $row->low_stock_threshold,
                ]);
            }
            fclose($handle);
        }, 'low-stock-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{store_id: int, date_from: Carbon, date_to: Carbon, warehouse_id: ?int, granularity: string}
     */
    private function resolveFilters(Request $request): array
    {
        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'date_from' => ['required', 'date'],
            'date_to' => [
                'required', 'date', 'after_or_equal:date_from',
                function ($attribute, $value, $fail) use ($request) {
                    if (Carbon::parse($request->input('date_from'))->diffInDays(Carbon::parse($value)) > 366) {
                        $fail('The date range cannot exceed 366 days.');
                    }
                },
            ],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ]);

        return [
            'store_id' => $request->integer('store_id'),
            'date_from' => Carbon::parse($request->string('date_from'))->startOfDay(),
            'date_to' => Carbon::parse($request->string('date_to'))->endOfDay(),
            'warehouse_id' => $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            'granularity' => $request->string('granularity', 'day')->toString(),
        ];
    }

    /** @return Collection<int, object{date: string, orders_count: int, revenue_minor: int}> */
    private function dailySalesRows(array $data): Collection
    {
        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.store_id', $data['store_id'])
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$data['date_from'], $data['date_to']])
            ->when($data['warehouse_id'], fn ($q) => $q->where('orders.warehouse_id', $data['warehouse_id']))
            ->selectRaw('DATE(orders.created_at) as date, COUNT(DISTINCT orders.id) as orders_count, SUM(order_items.quantity * order_items.unit_price_amount) as revenue_minor')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Grouping by week/month happens in PHP over already-fetched day
     * buckets rather than in SQL, since MySQL and SQLite (used by the test
     * suite) don't share a portable week/month truncation function —
     * DATE(orders.created_at) for the day-level query above is the only
     * date function both dialects agree on.
     */
    private function foldByGranularity(Collection $dayRows, string $granularity, string $currencyCode): array
    {
        if ($granularity === 'day') {
            return $dayRows->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'orders_count' => (int) $row->orders_count,
                'revenue_amount' => (new Money((int) $row->revenue_minor, $currencyCode))->toDecimal(),
            ])->values()->all();
        }

        $buckets = [];
        foreach ($dayRows as $row) {
            $date = Carbon::parse($row->date);
            $key = $granularity === 'week'
                ? $date->copy()->startOfWeek()->toDateString()
                : $date->copy()->startOfMonth()->toDateString();

            $buckets[$key] ??= ['orders_count' => 0, 'revenue_minor' => 0];
            $buckets[$key]['orders_count'] += $row->orders_count;
            $buckets[$key]['revenue_minor'] += $row->revenue_minor;
        }

        ksort($buckets);

        return collect($buckets)->map(fn ($bucket, $date) => [
            'date' => $date,
            'orders_count' => (int) $bucket['orders_count'],
            'revenue_amount' => (new Money((int) $bucket['revenue_minor'], $currencyCode))->toDecimal(),
        ])->values()->all();
    }

    private function paymentMethodQuery(array $data)
    {
        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.store_id', $data['store_id'])
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$data['date_from'], $data['date_to']])
            ->when($data['warehouse_id'], fn ($q) => $q->where('orders.warehouse_id', $data['warehouse_id']))
            ->selectRaw('orders.payment_method, COUNT(DISTINCT orders.id) as orders_count, SUM(order_items.quantity * order_items.unit_price_amount) as revenue_minor')
            ->groupBy('orders.payment_method');
    }

    /**
     * Inner-joined to `shipments`/`couriers` — unlike the totals/by-period/
     * by-payment-method breakdowns above, this one only covers orders that
     * actually reached a courier. An order still awaiting dispatch isn't
     * "this courier's" or any courier's yet, so it correctly drops out of
     * this breakdown while still counting in the report's overall totals;
     * the two are expected to disagree once orders are in flight.
     */
    private function courierQuery(array $data)
    {
        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('shipments', 'shipments.order_id', '=', 'orders.id')
            ->join('couriers', 'couriers.id', '=', 'shipments.courier_id')
            ->where('orders.store_id', $data['store_id'])
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$data['date_from'], $data['date_to']])
            ->when($data['warehouse_id'], fn ($q) => $q->where('orders.warehouse_id', $data['warehouse_id']))
            ->selectRaw('couriers.id as courier_id, couriers.name as courier_name, COUNT(DISTINCT orders.id) as orders_count, SUM(order_items.quantity * order_items.unit_price_amount) as revenue_minor')
            ->groupBy('couriers.id', 'couriers.name')
            ->orderByDesc('revenue_minor');
    }

    private function productPerformanceQuery(array $data)
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $data['store_id'])
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$data['date_from'], $data['date_to']])
            ->when($data['warehouse_id'], fn ($q) => $q->where('orders.warehouse_id', $data['warehouse_id']))
            ->selectRaw('products.id as product_id, products.name, products.sku, SUM(order_items.quantity) as units_sold, SUM(order_items.quantity * order_items.unit_price_amount) as revenue_minor')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue_minor');
    }

    private function lowStockQuery(int $storeId)
    {
        return DB::table('products')
            ->leftJoin('stock_levels', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.store_id', $storeId)
            ->where('products.track_stock', true)
            ->whereNotNull('products.low_stock_threshold')
            ->selectRaw('products.id as product_id, products.name, products.sku, products.low_stock_threshold, COALESCE(SUM(stock_levels.quantity), 0) as total_quantity, COALESCE(SUM(stock_levels.quantity_reserved), 0) as total_reserved')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.low_stock_threshold')
            ->havingRaw('(COALESCE(SUM(stock_levels.quantity), 0) - COALESCE(SUM(stock_levels.quantity_reserved), 0)) <= products.low_stock_threshold')
            ->orderBy('products.name');
    }
}
