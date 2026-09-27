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
use Illuminate\Support\Facades\DB;

/**
 * Dashboard aggregates span multiple models (orders/order_items) rather
 * than mapping to one Eloquent policy, so — same as
 * StockLevelController::lowStockCount() — these check a permission
 * directly instead of going through a resource policy.
 */
class DashboardController extends Controller
{
    /**
     * Orders placed and revenue booked per day for the trailing N days
     * (default 14, max 90). Cancelled orders are excluded — this is a
     * sales-activity trend, not an audit of every order ever created.
     * Revenue is the sum of order_items line totals (quantity x
     * unit_price_amount); it deliberately excludes shipping/discount,
     * so it won't exactly match an individual order's `total_amount` —
     * a per-order figure belongs on the order itself, this is a trend.
     * Days with no orders are zero-filled so the chart's x-axis is
     * continuous.
     */
    public function salesTrend(Request $request): JsonResponse
    {
        if (! $request->user()->can('orders.view')) {
            throw new AuthorizationException;
        }

        $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $storeId = $request->integer('store_id');
        $days = $request->integer('days', 14);
        $currencyCode = Store::find($storeId)?->currency?->code ?? 'BDT';
        $start = Carbon::today()->subDays($days - 1);

        $rows = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('DATE(orders.created_at) as date, COUNT(DISTINCT orders.id) as orders_count, SUM(order_items.quantity * order_items.unit_price_amount) as revenue_minor')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $row = $rows->get($date);

            $trend[] = [
                'date' => $date,
                'orders_count' => $row?->orders_count ?? 0,
                'revenue_amount' => (new Money((int) ($row?->revenue_minor ?? 0), $currencyCode))->toDecimal(),
            ];
        }

        return ApiResponse::success($trend, 'Sales trend fetched successfully.');
    }

    /** Order counts grouped by status, zero-filled for every known status. */
    public function orderStatusBreakdown(Request $request): JsonResponse
    {
        if (! $request->user()->can('orders.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);

        $counts = DB::table('orders')
            ->where('store_id', $request->integer('store_id'))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = [];
        foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status) {
            $breakdown[$status] = (int) ($counts[$status] ?? 0);
        }

        return ApiResponse::success($breakdown, 'Order status breakdown fetched successfully.');
    }
}
