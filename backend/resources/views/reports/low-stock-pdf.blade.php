<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('reports.partials.styles')</style>
</head>
<body>
@include('reports.partials.header', [
    'storeName' => $storeName,
    'reportTitle' => 'Low Stock Report',
    'subtitle' => $subtitle,
    'generatedAt' => $generatedAt,
])

<table>
    <thead>
    <tr>
        <th>Product</th>
        <th>SKU</th>
        <th class="text-right">On Hand</th>
        <th class="text-right">Reserved</th>
        <th class="text-right">Available</th>
        <th class="text-right">Threshold</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['sku'] }}</td>
            <td class="text-right">{{ $row['total_quantity'] }}</td>
            <td class="text-right">{{ $row['total_reserved'] }}</td>
            <td class="text-right">{{ (int) $row['total_quantity'] - (int) $row['total_reserved'] }}</td>
            <td class="text-right">{{ $row['low_stock_threshold'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Nothing is currently low on stock.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
