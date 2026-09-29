<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('reports.partials.styles')</style>
</head>
<body>
@include('reports.partials.header', [
    'storeName' => $storeName,
    'reportTitle' => 'Reorder Suggestions',
    'subtitle' => $subtitle,
    'generatedAt' => $generatedAt,
])

<table>
    <thead>
    <tr>
        <th>Product</th>
        <th>SKU</th>
        <th class="text-right">Available</th>
        <th class="text-right">Threshold</th>
        <th class="text-right">Avg. daily sales</th>
        <th class="text-right">Suggested reorder qty</th>
        <th>Last supplier</th>
        <th class="text-right">Last unit cost</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['sku'] }}</td>
            <td class="text-right">{{ $row['available_quantity'] }}</td>
            <td class="text-right">{{ $row['low_stock_threshold'] }}</td>
            <td class="text-right">{{ $row['avg_daily_sales'] }}</td>
            <td class="text-right">{{ $row['suggested_reorder_quantity'] }}</td>
            <td>{{ $row['last_supplier']['name'] ?? '—' }}</td>
            <td class="text-right">{{ $row['last_unit_cost'] ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="8">Nothing needs reordering right now.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
