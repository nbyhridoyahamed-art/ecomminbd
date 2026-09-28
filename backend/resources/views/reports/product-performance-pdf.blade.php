<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('reports.partials.styles')</style>
</head>
<body>
@include('reports.partials.header', [
    'storeName' => $storeName,
    'reportTitle' => 'Product Performance Report',
    'subtitle' => $subtitle,
    'generatedAt' => $generatedAt,
])

<table>
    <thead>
    <tr>
        <th>Product</th>
        <th>SKU</th>
        <th class="text-right">Units Sold</th>
        <th class="text-right">Revenue</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['sku'] }}</td>
            <td class="text-right">{{ $row['units_sold'] }}</td>
            <td class="text-right">{{ $currencyCode }} {{ number_format($row['revenue_amount'], 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="4">No sales in range.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
