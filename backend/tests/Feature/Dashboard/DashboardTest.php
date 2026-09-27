<?php

namespace Tests\Feature\Dashboard;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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
    private function orderOn(Store $store, Warehouse $warehouse, Customer $customer, string $status, string $createdAt, int $quantity, int $unitPriceMinor): Order
    {
        $product = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => $status]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price_amount' => $unitPriceMinor]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_sales_trend_zero_fills_days_with_no_orders_and_sums_revenue_from_line_items(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();

        // Today: 2 orders (100.00 + 50.00 = 150.00, 3 units).
        $this->orderOn($store, $warehouse, $customer, 'pending', now()->toDateTimeString(), 1, 10000);
        $this->orderOn($store, $warehouse, $customer, 'delivered', now()->toDateTimeString(), 2, 2500);
        // 3 days ago: 1 order (200.00).
        $this->orderOn($store, $warehouse, $customer, 'shipped', now()->subDays(3)->toDateTimeString(), 1, 20000);
        // Yesterday: a cancelled order — must be excluded entirely.
        $this->orderOn($store, $warehouse, $customer, 'cancelled', now()->subDay()->toDateTimeString(), 1, 99999);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/dashboard/sales-trend?store_id={$store->id}&days=7")
            ->assertOk();

        $trend = collect($response->json('data'))->keyBy('date');

        $this->assertCount(7, $trend);
        $this->assertSame(2, $trend[now()->toDateString()]['orders_count']);
        $this->assertEquals(150.0, $trend[now()->toDateString()]['revenue_amount']);
        $this->assertSame(1, $trend[now()->subDays(3)->toDateString()]['orders_count']);
        $this->assertEquals(200.0, $trend[now()->subDays(3)->toDateString()]['revenue_amount']);
        // The cancelled order's day has no other orders — must zero-fill, not disappear.
        $this->assertSame(0, $trend[now()->subDay()->toDateString()]['orders_count']);
        $this->assertEquals(0.0, $trend[now()->subDay()->toDateString()]['revenue_amount']);
        // A day with nothing at all.
        $this->assertSame(0, $trend[now()->subDays(5)->toDateString()]['orders_count']);
    }

    public function test_order_status_breakdown_counts_every_known_status_including_zero(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();

        Order::factory()->for($store)->for($customer)->for($warehouse)->count(2)->create(['status' => 'pending']);
        Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => 'delivered']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/dashboard/order-status-breakdown?store_id={$store->id}")
            ->assertOk();

        $response->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.delivered', 1)
            ->assertJsonPath('data.processing', 0)
            ->assertJsonPath('data.shipped', 0)
            ->assertJsonPath('data.cancelled', 0);
    }

    public function test_a_user_without_orders_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Inventory Manager');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/dashboard/sales-trend?store_id={$store->id}")
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/dashboard/order-status-breakdown?store_id={$store->id}")
            ->assertForbidden();
    }
}
