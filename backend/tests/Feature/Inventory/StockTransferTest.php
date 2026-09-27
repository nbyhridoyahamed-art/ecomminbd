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

class StockTransferTest extends TestCase
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

    public function test_a_transfer_moves_stock_between_warehouses_and_logs_paired_movements(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'note' => 'Rebalancing',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 12],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity', 12);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 18]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $to->id, 'quantity' => 12]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $from->id, 'type' => 'transfer_out', 'quantity' => 12,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $to->id, 'type' => 'transfer_in', 'quantity' => 12,
        ]);
    }

    public function test_a_transfer_with_insufficient_source_stock_is_rejected_and_nothing_moves(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 5]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 50],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_a_transfer_with_multiple_items_rolls_back_entirely_if_one_item_is_insufficient(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $plentiful = Product::factory()->for($store)->create();
        $scarce = Product::factory()->for($store)->create();
        StockLevel::factory()->for($plentiful)->for($from)->create(['quantity' => 100]);
        StockLevel::factory()->for($scarce)->for($from)->create(['quantity' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'items' => [
                    ['product_id' => $plentiful->id, 'quantity' => 10],
                    ['product_id' => $scarce->id, 'quantity' => 5],
                ],
            ])
            ->assertStatus(422);

        // The plentiful item's movement must not have been committed either.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $plentiful->id, 'warehouse_id' => $from->id, 'quantity' => 100]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_from_and_to_warehouse_must_differ(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $warehouse->id,
                'to_warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_warehouse_id');
    }

    public function test_a_user_without_inventory_transfer_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ])
            ->assertForbidden();
    }
}
