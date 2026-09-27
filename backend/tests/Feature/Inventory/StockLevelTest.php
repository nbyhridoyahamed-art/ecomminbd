<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLevelTest extends TestCase
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

    public function test_a_product_with_no_movement_shows_zero_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        Product::factory()->for($store)->create(['name' => 'Untouched Widget']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-levels?store_id={$store->id}&warehouse_id={$warehouse->id}")
            ->assertOk()
            ->assertJsonPath('data.0.quantity', 0)
            ->assertJsonPath('data.0.is_low_stock', false);
    }

    public function test_stock_level_reflects_the_warehouse_specific_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouseA)->create(['quantity' => 40]);
        StockLevel::factory()->for($product)->for($warehouseB)->create(['quantity' => 5]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-levels?store_id={$store->id}&warehouse_id={$warehouseA->id}")
            ->assertOk()
            ->assertJsonPath('data.0.quantity', 40);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-levels?store_id={$store->id}&warehouse_id={$warehouseB->id}")
            ->assertOk()
            ->assertJsonPath('data.0.quantity', 5);
    }

    public function test_low_stock_filter_only_returns_products_at_or_below_threshold(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $low = Product::factory()->for($store)->create(['name' => 'Low Stock Item', 'low_stock_threshold' => 10, 'track_stock' => true]);
        $healthy = Product::factory()->for($store)->create(['name' => 'Healthy Item', 'low_stock_threshold' => 10, 'track_stock' => true]);
        StockLevel::factory()->for($low)->for($warehouse)->create(['quantity' => 3]);
        StockLevel::factory()->for($healthy)->for($warehouse)->create(['quantity' => 50]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-levels?store_id={$store->id}&warehouse_id={$warehouse->id}&low_stock=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'Low Stock Item')
            ->assertJsonPath('data.0.is_low_stock', true);
    }

    public function test_a_user_without_inventory_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager');
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/stock-levels?store_id={$store->id}&warehouse_id={$warehouse->id}")
            ->assertForbidden();
    }

    public function test_low_stock_count_sums_across_all_of_the_stores_warehouses(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();

        $low1 = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        $low2 = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        $healthy = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => true]);
        $untracked = Product::factory()->for($store)->create(['low_stock_threshold' => 10, 'track_stock' => false]);
        $otherStoreProduct = Product::factory()->for($otherStore)->create(['low_stock_threshold' => 10, 'track_stock' => true]);

        StockLevel::factory()->for($low1)->for($warehouseA)->create(['quantity' => 2]);
        StockLevel::factory()->for($low2)->for($warehouseB)->create(['quantity' => 0]);
        StockLevel::factory()->for($healthy)->for($warehouseA)->create(['quantity' => 99]);
        StockLevel::factory()->for($untracked)->for($warehouseA)->create(['quantity' => 1]);
        StockLevel::factory()->for($otherStoreProduct)->for(Warehouse::factory()->for($otherStore)->create())->create(['quantity' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-levels/low-stock-count?store_id={$store->id}")
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }
}
