<?php

namespace Tests\Feature\Reporting;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderSuggestionsTest extends TestCase
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

    private function orderOn(Store $store, Warehouse $warehouse, Customer $customer, Product $product, int $quantity, string $createdAt, string $status = 'delivered'): Order
    {
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => $status]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price_amount' => 1000]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_reorder_suggestions_combines_low_stock_velocity_and_last_supplier(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create(['name' => 'Acme Supplies']);

        $product = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'quantity_reserved' => 0]);

        // 6 units sold across two orders in the last 30 days -> avg 0.2/day.
        $this->orderOn($store, $warehouse, $customer, $product, 3, now()->subDays(5)->toDateTimeString());
        $this->orderOn($store, $warehouse, $customer, $product, 3, now()->subDays(10)->toDateTimeString());

        $po = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->ordered()->create();
        $po->items()->create(['product_id' => $product->id, 'quantity_ordered' => 20, 'unit_cost_amount' => 5000]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/reorder-suggestions?store_id={$store->id}")
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('product_id');
        $row = $rows[$product->id];

        $this->assertSame(5, $row['available_quantity']);
        $this->assertSame(10, $row['low_stock_threshold']);
        $this->assertEquals(0.2, $row['avg_daily_sales']);
        // Deficit (10 - 5 = 5) + ceil(0.2 * 14 lead-time days = 2.8 -> 3) = 8.
        $this->assertSame(8, $row['suggested_reorder_quantity']);
        $this->assertSame('Acme Supplies', $row['last_supplier']['name']);
        $this->assertEquals(50.0, $row['last_unit_cost']);
    }

    public function test_reorder_suggestions_ignores_sales_older_than_30_days_and_cancelled_orders(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'quantity_reserved' => 0]);

        $this->orderOn($store, $warehouse, $customer, $product, 100, now()->subDays(40)->toDateTimeString());
        $this->orderOn($store, $warehouse, $customer, $product, 100, now()->subDays(5)->toDateTimeString(), status: 'cancelled');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/reorder-suggestions?store_id={$store->id}")
            ->assertOk();

        $row = collect($response->json('data'))->keyBy('product_id')[$product->id];
        $this->assertEquals(0.0, $row['avg_daily_sales']);
        // No sales counted -> just the threshold deficit (10 - 5 = 5).
        $this->assertSame(5, $row['suggested_reorder_quantity']);
    }

    public function test_reorder_suggestions_uses_the_most_recent_non_cancelled_purchase_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1, 'quantity_reserved' => 0]);

        $olderSupplier = Supplier::factory()->for($store)->create(['name' => 'Older Supplier']);
        $olderPo = PurchaseOrder::factory()->for($store)->for($warehouse)->for($olderSupplier)->ordered()->create();
        $olderPo->items()->create(['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost_amount' => 1000]);
        $olderPo->forceFill(['created_at' => now()->subDays(20)])->save();

        $cancelledSupplier = Supplier::factory()->for($store)->create(['name' => 'Cancelled Supplier']);
        $cancelledPo = PurchaseOrder::factory()->for($store)->for($warehouse)->for($cancelledSupplier)->create(['status' => 'cancelled']);
        $cancelledPo->items()->create(['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost_amount' => 9999]);
        $cancelledPo->forceFill(['created_at' => now()->subDays(1)])->save();

        $newerSupplier = Supplier::factory()->for($store)->create(['name' => 'Newer Supplier']);
        $newerPo = PurchaseOrder::factory()->for($store)->for($warehouse)->for($newerSupplier)->ordered()->create();
        $newerPo->items()->create(['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost_amount' => 2000]);
        $newerPo->forceFill(['created_at' => now()->subDays(3)])->save();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/reorder-suggestions?store_id={$store->id}")
            ->assertOk();

        $row = collect($response->json('data'))->keyBy('product_id')[$product->id];
        $this->assertSame('Newer Supplier', $row['last_supplier']['name']);
        $this->assertEquals(20.0, $row['last_unit_cost']);
    }

    public function test_reorder_suggestions_requires_reports_view(): void
    {
        $store = Store::factory()->create();

        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/reports/reorder-suggestions?store_id={$store->id}")
            ->assertForbidden();
    }

    public function test_reorder_suggestions_export_streams_a_csv(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2, 'quantity_reserved' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/reports/reorder-suggestions/export?store_id={$store->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
