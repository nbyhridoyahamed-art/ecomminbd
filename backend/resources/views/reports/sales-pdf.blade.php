<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('reports.partials.styles')</style>
</head>
<body>
@include('reports.partials.header', [
    'storeName' => $storeName,
    'reportTitle' => 'Sales Report',
    'subtitle' => $subtitle,
    'generatedAt' => $generatedAt,
])

<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-label">Revenue</div>
            <div class="kpi-value">{{ $currencyCode }} {{ number_format($totals['revenue_amount'], 2) }}</div>
            <div class="trend-{{ $revenueTrend['direction'] }}">{{ $revenueTrend['label'] }}</div>
        </td>
        <td>
            <div class="kpi-label">Orders</div>
            <div class="kpi-value">{{ $totals['orders_count'] }}</div>
            <div class="trend-{{ $ordersTrend['direction'] }}">{{ $ordersTrend['label'] }}</div>
        </td>
        <td>
            <div class="kpi-label">Average Order Value</div>
            <div class="kpi-value">{{ $currencyCode }} {{ number_format($totals['average_order_value'], 2) }}</div>
            <div class="trend-{{ $aovTrend['direction'] }}">{{ $aovTrend['label'] }}</div>
        </td>
    </tr>
</table>

<p class="section-title">By period</p>
<table>
    <thead>
    <tr><th>Date</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
    </thead>
    <tbody>
    @forelse ($byPeriod as $row)
        <tr>
            <td>{{ $row['date'] }}</td>
            <td class="text-right">{{ $row['orders_count'] }}</td>
            <td class="text-right">{{ $currencyCode }} {{ number_format($row['revenue_amount'], 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No orders in range.</td></tr>
    @endforelse
    </tbody>
</table>

<p class="section-title">By payment method</p>
<table>
    <thead>
    <tr><th>Payment method</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
    </thead>
    <tbody>
    @forelse ($byPaymentMethod as $row)
        <tr>
            <td>{{ ucfirst($row['payment_method']) }}</td>
            <td class="text-right">{{ $row['orders_count'] }}</td>
            <td class="text-right">{{ $currencyCode }} {{ number_format($row['revenue_amount'], 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No orders in range.</td></tr>
    @endforelse
    </tbody>
</table>

<p class="section-title">By courier</p>
<table>
    <thead>
    <tr><th>Courier</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
    </thead>
    <tbody>
    @forelse ($byCourier as $row)
        <tr>
            <td>{{ $row['courier_name'] }}</td>
            <td class="text-right">{{ $row['orders_count'] }}</td>
            <td class="text-right">{{ $currencyCode }} {{ number_format($row['revenue_amount'], 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No dispatched orders in range.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
