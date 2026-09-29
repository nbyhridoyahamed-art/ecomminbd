<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockTransfer;
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

    public function test_creating_a_transfer_is_pending_and_moves_no_stock_yet(): void
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
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.items.0.quantity', 12)
            ->assertJsonPath('data.status_history.0.to_status', 'pending');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 30]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    private function createTransfer(User $admin, Store $store, Warehouse $from, Warehouse $to, array $items, ?string $note = null): array
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'note' => $note,
                'items' => $items,
            ])
            ->assertCreated()
            ->json('data');
    }

    public function test_shipping_a_pending_transfer_decrements_source_and_logs_a_transfer_out_movement(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 18]);
        // Nothing has arrived at the destination yet — that's receive()'s job.
        $this->assertDatabaseMissing('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $to->id]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $from->id, 'type' => 'transfer_out', 'quantity' => 12,
            'reference_type' => StockTransfer::class, 'reference_id' => $transfer['id'],
        ]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_receiving_an_in_transit_transfer_increments_destination_and_logs_a_movement(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 18]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $to->id, 'quantity' => 12]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $to->id, 'type' => 'transfer_in', 'quantity' => 12,
            'reference_type' => StockTransfer::class, 'reference_id' => $transfer['id'],
        ]);
        $this->assertDatabaseCount('stock_movements', 2);

        $history = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-transfers/{$transfer['id']}")
            ->json('data.status_history');
        $this->assertSame(['pending', 'in_transit', 'received'], array_column($history, 'to_status'));
    }

    public function test_cancelling_a_pending_transfer_has_no_stock_impact(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel", ['note' => 'Requested by mistake'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 30]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_an_already_shipped_transfer_cannot_be_cancelled(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel")
            ->assertStatus(422);
    }

    public function test_a_pending_transfer_cannot_be_received_before_shipping(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive")
            ->assertStatus(422);
    }

    public function test_an_already_shipped_transfer_cannot_be_shipped_again(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")
            ->assertStatus(422);
    }

    /** @return array{product: Product, variant: ProductVariant} */
    private function variantProduct(Store $store): array
    {
        $product = Product::factory()->for($store)->create(['type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color-'.$product->id]);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red-'.$product->id]);

        $variant = ProductVariant::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-RED',
            'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        return compact('product', 'variant');
    }

    public function test_a_transfer_of_a_variant_moves_only_that_variants_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        ['product' => $product, 'variant' => $variant] = $this->variantProduct($store);

        // A decoy variant-less row for the same product — transferring the
        // variant must never touch this one.
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 999]);
        StockLevel::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $from->id, 'quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 12],
        ]);
        $this->assertSame($variant->id, $transfer['items'][0]['product_variant']['id']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive")->assertOk();

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $from->id, 'quantity' => 18]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $to->id, 'quantity' => 12]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $from->id, 'quantity' => 999]);
    }

    public function test_a_bundle_product_is_rejected_from_stock_transfers(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-transfers', [
                'store_id' => $store->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'items' => [['product_id' => $bundle->id, 'quantity' => 5]],
            ])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_shipping_with_insufficient_source_stock_is_rejected_and_nothing_moves(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 5]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 50],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")
            ->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $from->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer['id'], 'status' => 'pending']);
    }

    public function test_shipping_with_multiple_items_rolls_back_entirely_if_one_item_is_insufficient(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $plentiful = Product::factory()->for($store)->create();
        $scarce = Product::factory()->for($store)->create();
        StockLevel::factory()->for($plentiful)->for($from)->create(['quantity' => 100]);
        StockLevel::factory()->for($scarce)->for($from)->create(['quantity' => 1]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $plentiful->id, 'quantity' => 10],
            ['product_id' => $scarce->id, 'quantity' => 5],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")
            ->assertStatus(422);

        // The plentiful item's movement must not have been committed either.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $plentiful->id, 'warehouse_id' => $from->id, 'quantity' => 100]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer['id'], 'status' => 'pending']);
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

    public function test_a_user_without_inventory_transfer_cannot_ship_receive_or_cancel(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $from = Warehouse::factory()->for($store)->create();
        $to = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($from)->create(['quantity' => 30]);

        $transfer = $this->createTransfer($admin, $store, $from, $to, [
            ['product_id' => $product->id, 'quantity' => 12],
        ]);

        $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/ship")->assertForbidden();
        $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel")->assertForbidden();
    }
}
