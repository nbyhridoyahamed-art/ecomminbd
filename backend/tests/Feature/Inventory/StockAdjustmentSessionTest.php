<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\StockAdjustmentSession;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentSessionTest extends TestCase
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

    public function test_a_session_applies_every_line_and_tags_its_movements_to_the_session(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $productA = Product::factory()->for($store)->create();
        $productB = Product::factory()->for($store)->create();
        StockLevel::factory()->for($productA)->for($warehouse)->create(['quantity' => 100]);
        StockLevel::factory()->for($productB)->for($warehouse)->create(['quantity' => 20]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'reference' => 'STOCKTAKE-2026-09',
                'note' => 'Quarterly count',
                'items' => [
                    ['product_id' => $productA->id, 'direction' => 'decrease', 'quantity' => 3, 'reason' => 'Damaged'],
                    ['product_id' => $productB->id, 'direction' => 'increase', 'quantity' => 5, 'reason' => 'Found extra units'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.reference', 'STOCKTAKE-2026-09')
            ->assertJsonCount(2, 'data.movements');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $productA->id, 'warehouse_id' => $warehouse->id, 'quantity' => 97]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $productB->id, 'warehouse_id' => $warehouse->id, 'quantity' => 25]);

        $sessionId = $response->json('data.id');
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id, 'type' => 'adjustment_decrease', 'quantity' => 3,
            'reference_type' => StockAdjustmentSession::class, 'reference_id' => $sessionId,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id, 'type' => 'adjustment_increase', 'quantity' => 5,
            'reference_type' => StockAdjustmentSession::class, 'reference_id' => $sessionId,
        ]);
    }

    public function test_a_reference_is_auto_generated_when_omitted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'direction' => 'increase', 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.reference', fn ($reference) => is_string($reference) && $reference !== '');
    }

    public function test_a_duplicate_reference_within_the_same_store_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10]);
        StockAdjustmentSession::factory()->for($store)->for($warehouse)->create(['reference' => 'DUPE-REF']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'reference' => 'DUPE-REF',
                'items' => [['product_id' => $product->id, 'direction' => 'increase', 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_a_session_rolls_back_entirely_if_one_line_is_insufficient(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $plentiful = Product::factory()->for($store)->create();
        $scarce = Product::factory()->for($store)->create();
        StockLevel::factory()->for($plentiful)->for($warehouse)->create(['quantity' => 100]);
        StockLevel::factory()->for($scarce)->for($warehouse)->create(['quantity' => 2]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $plentiful->id, 'direction' => 'decrease', 'quantity' => 10],
                    ['product_id' => $scarce->id, 'direction' => 'decrease', 'quantity' => 20],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $plentiful->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_adjustment_sessions', 0);
    }

    public function test_a_bundle_product_is_rejected_from_a_session(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $bundle->id, 'direction' => 'increase', 'quantity' => 5]],
            ])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_the_same_product_cannot_appear_twice_in_one_session(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'direction' => 'increase', 'quantity' => 5],
                    ['product_id' => $product->id, 'direction' => 'decrease', 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_warehouse_must_belong_to_the_selected_store(): void
    {
        $admin = $this->admin();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($storeA)->create();
        $product = Product::factory()->for($storeB)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $storeB->id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'direction' => 'increase', 'quantity' => 5]],
            ])
            ->assertStatus(422);
    }

    public function test_a_user_without_inventory_adjust_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/stock-adjustment-sessions', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'direction' => 'increase', 'quantity' => 5]],
            ])
            ->assertForbidden();
    }

    public function test_sessions_can_be_listed_and_viewed(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $session = StockAdjustmentSession::factory()->for($store)->for($warehouse)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-adjustment-sessions?store_id={$store->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-adjustment-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }
}
