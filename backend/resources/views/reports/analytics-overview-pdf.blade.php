<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('reports.partials.styles')</style>
</head>
<body>
@include('reports.partials.header', [
    'storeName' => $storeName,
    'reportTitle' => 'Analytics Overview',
    'subtitle' => $subtitle,
    'generatedAt' => $generatedAt,
])

<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-label">Page Views</div>
            <div class="kpi-value">{{ number_format($totals['page_views']) }}</div>
            <div class="trend-{{ $pageViewsTrend['direction'] }}">{{ $pageViewsTrend['label'] }}</div>
        </td>
        <td>
            <div class="kpi-label">Unique Sessions</div>
            <div class="kpi-value">{{ number_format($totals['unique_sessions']) }}</div>
            <div class="trend-{{ $sessionsTrend['direction'] }}">{{ $sessionsTrend['label'] }}</div>
        </td>
        <td>
            <div class="kpi-label">Conversion Rate</div>
            <div class="kpi-value">{{ number_format($totals['conversion_rate'], 2) }}%</div>
            <div class="trend-{{ $conversionTrend['direction'] }}">{{ $conversionTrend['label'] }}</div>
        </td>
    </tr>
</table>

<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-label">Product Views</div>
            <div class="kpi-value">{{ number_format($totals['product_views']) }}</div>
        </td>
        <td>
            <div class="kpi-label">Searches</div>
            <div class="kpi-value">{{ number_format($totals['searches']) }}</div>
        </td>
        <td>
            <div class="kpi-label">Added to Cart</div>
            <div class="kpi-value">{{ number_format($totals['add_to_cart']) }}</div>
        </td>
        <td>
            <div class="kpi-label">Checkouts Started</div>
            <div class="kpi-value">{{ number_format($totals['checkout_starts']) }}</div>
        </td>
    </tr>
</table>

<p class="section-title">By period</p>
<table>
    <thead>
    <tr><th>Date</th><th class="text-right">Page Views</th><th class="text-right">Unique Sessions</th></tr>
    </thead>
    <tbody>
    @forelse ($byPeriod as $row)
        <tr>
            <td>{{ $row['date'] }}</td>
            <td class="text-right">{{ $row['page_views'] }}</td>
            <td class="text-right">{{ $row['unique_sessions'] }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No traffic in range.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
