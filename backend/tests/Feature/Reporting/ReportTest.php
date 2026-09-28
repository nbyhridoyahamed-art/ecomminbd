<?php

namespace Tests\Feature\Reporting;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    /** An order with one item (qty x @ unitPrice minor units), backdated to $createdAt. */
    private function orderOn(
        Store $store,
        Warehouse $warehouse,
        Customer $customer,
        string $status,
        string $paymentMethod,
        string $createdAt,
        Product $product,
        int $quantity,
        int $unitPriceMinor,
        ?int $variantId = null,
    ): Order {
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)
            ->create(['status' => $status, 'payment_method' => $paymentMethod]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price_amount' => $unitPriceMinor,
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_sales_report_computes_totals_by_period_and_by_payment_method(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 10000);
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'bkash', '2026-06-10 12:00:00', $product, 2, 2500);
        $this->orderOn($store, $warehouse, $customer, 'shipped', 'cod', '2026-06-12 09:00:00', $product, 1, 20000);
        // Cancelled — must be excluded.
        $this->orderOn($store, $warehouse, $customer, 'cancelled', 'cod', '2026-06-11 09:00:00', $product, 1, 99999);
        // Outside the requested range — must be excluded.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-07-01 09:00:00', $product, 1, 50000);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $response->assertJsonPath('data.totals.orders_count', 3)
            ->assertJsonPath('data.totals.revenue_amount', 350);

        $byPeriod = collect($response->json('data.by_period'))->keyBy('date');
        $this->assertEquals(150.0, $byPeriod['2026-06-10']['revenue_amount']);
        $this->assertSame(2, $byPeriod['2026-06-10']['orders_count']);
        $this->assertEquals(200.0, $byPeriod['2026-06-12']['revenue_amount']);

        $byMethod = collect($response->json('data.by_payment_method'))->keyBy('payment_method');
        $this->assertEquals(300.0, $byMethod['cod']['revenue_amount']);
        $this->assertSame(2, $byMethod['cod']['orders_count']);
        $this->assertEquals(50.0, $byMethod['bkash']['revenue_amount']);
    }

    public function test_sales_report_computes_by_courier_breakdown_from_shipped_orders_only(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $courierA = Courier::factory()->for($store)->create(['name' => 'Courier A']);
        $courierB = Courier::factory()->for($store)->create(['name' => 'Courier B']);

        $order1 = $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 10000);
        Shipment::factory()->for($store)->for($order1)->for($courierA)->create();

        $order2 = $this->orderOn($store, $warehouse, $customer, 'shipped', 'cod', '2026-06-11 10:00:00', $product, 1, 5000);
        Shipment::factory()->for($store)->for($order2)->for($courierA)->create();

        $order3 = $this->orderOn($store, $warehouse, $customer, 'delivered', 'bkash', '2026-06-12 10:00:00', $product, 1, 8000);
        Shipment::factory()->for($store)->for($order3)->for($courierB)->create();

        // Not yet dispatched — must count in totals but not appear under any courier.
        $this->orderOn($store, $warehouse, $customer, 'processing', 'cod', '2026-06-13 10:00:00', $product, 1, 20000);

        // Cancelled, even though it has a shipment — must be excluded from both.
        $order5 = $this->orderOn($store, $warehouse, $customer, 'cancelled', 'cod', '2026-06-14 10:00:00', $product, 1, 99999);
        Shipment::factory()->for($store)->for($order5)->for($courierA)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $response->assertJsonPath('data.totals.orders_count', 4);

        $byCourier = collect($response->json('data.by_courier'))->keyBy('courier_name');
        $this->assertCount(2, $byCourier);
        $this->assertSame(2, $byCourier['Courier A']['orders_count']);
        $this->assertEquals(150.0, $byCourier['Courier A']['revenue_amount']);
        $this->assertSame(1, $byCourier['Courier B']['orders_count']);
        $this->assertEquals(80.0, $byCourier['Courier B']['revenue_amount']);
    }

    public function test_sales_report_includes_period_over_period_comparison(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $otherWarehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        // Requested range: 2026-06-10..2026-06-19 (10 days).
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-15 10:00:00', $product, 1, 20000);

        // The previous period is the 10 days right before it: 2026-05-31..2026-06-09.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-05 10:00:00', $product, 1, 10000);
        // One day before the previous period starts — must be excluded.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-05-30 10:00:00', $product, 1, 99999);
        // Inside the previous period but a different warehouse — excluded once warehouse-filtered.
        $this->orderOn($store, $otherWarehouse, $customer, 'delivered', 'cod', '2026-06-01 10:00:00', $product, 1, 50000);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-10&date_to=2026-06-19&warehouse_id={$warehouse->id}")
            ->assertOk();

        $response->assertJsonPath('data.totals.revenue_amount', 200)
            ->assertJsonPath('data.comparison.date_from', '2026-05-31')
            ->assertJsonPath('data.comparison.date_to', '2026-06-09')
            ->assertJsonPath('data.comparison.totals.revenue_amount', 100)
            ->assertJsonPath('data.comparison.totals.orders_count', 1)
            ->assertJsonPath('data.comparison.totals.average_order_value', 100);
    }

    public function test_sales_report_folds_days_into_weeks_when_requested(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        // Monday and Wednesday of the same ISO week.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-08 10:00:00', $product, 1, 10000);
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 5000);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30&granularity=week")
            ->assertOk();

        $byPeriod = collect($response->json('data.by_period'));
        $this->assertCount(1, $byPeriod->filter(fn ($row) => $row['date'] === '2026-06-08'));
        $this->assertEquals(150.0, $byPeriod->firstWhere('date', '2026-06-08')['revenue_amount']);
        $this->assertSame(2, $byPeriod->firstWhere('date', '2026-06-08')['orders_count']);
    }

    public function test_sales_report_can_be_filtered_by_warehouse(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->orderOn($store, $warehouseA, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 10000);
        $this->orderOn($store, $warehouseB, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 5000);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30&warehouse_id={$warehouseA->id}")
            ->assertOk();

        $response->assertJsonPath('data.totals.orders_count', 1)
            ->assertJsonPath('data.totals.revenue_amount', 100);
    }

    public function test_sales_report_rejects_a_date_range_longer_than_a_year(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2020-01-01&date_to=2026-01-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }

    public function test_sales_report_export_streams_a_csv(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 10000);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/reports/sales/export?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('2026-06-10', $response->streamedContent());
    }

    public function test_product_performance_ranks_by_revenue_and_rolls_up_variant_sales(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();

        $variableProduct = Product::factory()->for($store)->create(['name' => 'Variable Shirt', 'type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);
        $variant = ProductVariant::create([
            'store_id' => $store->id, 'product_id' => $variableProduct->id, 'sku' => 'SHIRT-RED', 'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        $simpleProduct = Product::factory()->for($store)->create(['name' => 'Simple Mug']);

        // Variable product sold via two different variant-less/variant order items — must roll up into one row.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $variableProduct, 2, 10000, $variant->id);
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-11 10:00:00', $variableProduct, 1, 10000, $variant->id);
        // Simple product — lower total revenue, should rank second.
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $simpleProduct, 1, 5000);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/products-performance?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(2, $rows);
        $this->assertSame('Variable Shirt', $rows[0]['name']);
        $this->assertSame(3, $rows[0]['units_sold']);
        $this->assertEquals(300.0, $rows[0]['revenue_amount']);
        $this->assertSame('Simple Mug', $rows[1]['name']);
    }

    public function test_product_performance_export_streams_a_csv(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['name' => 'Exported Product']);
        $this->orderOn($store, $warehouse, $customer, 'delivered', 'cod', '2026-06-10 10:00:00', $product, 1, 10000);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/reports/products-performance/export?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30");

        $response->assertOk();
        $this->assertStringContainsString('Exported Product', $response->streamedContent());
    }

    public function test_low_stock_report_sums_across_warehouses_and_variants(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();

        $lowProduct = Product::factory()->for($store)->create([
            'name' => 'Low Stock Item', 'low_stock_threshold' => 10, 'track_stock' => true,
        ]);
        StockLevel::create(['product_id' => $lowProduct->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 3, 'quantity_reserved' => 0]);
        StockLevel::create(['product_id' => $lowProduct->id, 'warehouse_id' => $warehouseB->id, 'quantity' => 2, 'quantity_reserved' => 0]);

        $healthyProduct = Product::factory()->for($store)->create([
            'name' => 'Healthy Item', 'low_stock_threshold' => 10, 'track_stock' => true,
        ]);
        StockLevel::create(['product_id' => $healthyProduct->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 99, 'quantity_reserved' => 0]);

        // No threshold set — must be excluded regardless of stock.
        Product::factory()->for($store)->create(['name' => 'No Threshold Item', 'low_stock_threshold' => null]);
        // track_stock disabled — must be excluded.
        Product::factory()->for($store)->create(['name' => 'Untracked Item', 'low_stock_threshold' => 5, 'track_stock' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/low-stock?store_id={$store->id}")
            ->assertOk();

        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('Low Stock Item', $rows[0]['name']);
        $this->assertSame(5, $rows[0]['total_quantity']);
        $this->assertSame(5, $rows[0]['total_available']);
    }

    public function test_low_stock_report_export_streams_a_csv(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['name' => 'Exported Low Item', 'low_stock_threshold' => 10]);
        StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->get("/api/v1/reports/low-stock/export?store_id={$store->id}");

        $response->assertOk();
        $this->assertStringContainsString('Exported Low Item', $response->streamedContent());
    }

    public function test_a_user_without_reports_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Content Manager');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/reports/sales?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertForbidden();
        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/reports/products-performance?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertForbidden();
        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/reports/low-stock?store_id={$store->id}")
            ->assertForbidden();
    }
}
