<?php

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
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

    /**
     * A purchase order with one item, already (partially or fully) received —
     * built directly via Eloquent, the same way ReturnTest::deliveredOrder()
     * builds its fixture, rather than replaying place()/receipts() calls.
     *
     * @return array{order: PurchaseOrder, item: PurchaseOrderItem, product: Product, warehouse: Warehouse}
     */
    private function receivedPurchaseOrder(
        Store $store,
        int $quantityOrdered = 10,
        int $quantityReceived = 10,
        int $unitCostMinor = 10000,
    ): array {
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $status = $quantityReceived <= 0 ? 'ordered' : ($quantityReceived >= $quantityOrdered ? 'received' : 'partially_received');

        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => $status]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => $quantityOrdered,
            'quantity_received' => $quantityReceived,
            'unit_cost_amount' => $unitCostMinor,
        ]);

        if ($quantityReceived > 0) {
            StockLevel::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantityReceived]);
        }

        return compact('order', 'item', 'product', 'warehouse');
    }

    public function test_a_purchase_return_can_be_requested_for_a_received_purchase_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
            'reason' => 'Damaged in transit',
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 3]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('purchase_return_status_history', ['to_status' => 'requested', 'from_status' => null]);
    }

    public function test_a_purchase_return_cannot_be_requested_when_nothing_has_been_received(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 0);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_a_purchase_return_cannot_request_more_than_the_received_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 5);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 6]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_an_open_purchase_return_reserves_the_returnable_quantity_but_a_rejected_one_frees_it_back_up(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 3, quantityReceived: 3);

        $firstReturnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
            ])
            ->assertCreated()->json('data.id');

        // Only 1 of the 3 received units remains eligible while the first
        // return is still open (requested).
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$firstReturnId}/reject")->assertOk();

        // Rejecting the first return frees its quantity back up.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
            ])
            ->assertCreated();
    }

    public function test_shipping_a_purchase_return_back_decrements_stock_and_creates_a_purchase_return_movement(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'product' => $product, 'warehouse' => $warehouse] =
            $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/approve")->assertOk();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/ship-back")
            ->assertOk()->assertJsonPath('data.status', 'shipped_back');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 6]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'purchase_return', 'quantity' => 4,
            'quantity_before' => 10, 'quantity_after' => 6,
            'reference_type' => PurchaseReturn::class, 'reference_id' => $returnId,
        ]);
    }

    public function test_shipping_back_a_purchase_return_with_insufficient_stock_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'product' => $product, 'warehouse' => $warehouse] =
            $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/approve")->assertOk();

        // Stock was sold/adjusted away to 2 units before the physical return
        // could actually be shipped back — only 2 remain, but 4 were requested.
        StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->update(['quantity' => 2]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/ship-back")
            ->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id, 'type' => 'purchase_return']);
        $this->assertDatabaseHas('purchase_returns', ['id' => $returnId, 'status' => 'approved']);
    }

    public function test_a_purchase_return_for_a_variant_item_decrements_only_that_variants_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();

        $product = Product::factory()->for($store)->create(['type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);
        $variant = ProductVariant::create([
            'store_id' => $store->id, 'product_id' => $product->id, 'sku' => $product->sku.'-RED', 'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'received']);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'quantity_ordered' => 10, 'quantity_received' => 10, 'unit_cost_amount' => 5000,
        ]);
        StockLevel::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);
        // A decoy variant-less row for the same product — the return must
        // never touch this one.
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 999]);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/ship-back")->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 6,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id, 'quantity' => 999,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'type' => 'purchase_return', 'quantity' => 4,
        ]);
    }

    public function test_a_purchase_return_can_be_rejected_from_requested_or_approved_but_not_after(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10);

        $requestedId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", ['items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$requestedId}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $approvedId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", ['items' => [['purchase_order_item_id' => $item->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$approvedId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$approvedId}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$approvedId}/ship-back")->assertStatus(422);
    }

    public function test_full_lifecycle_crediting_a_purchase_return_suggests_the_original_unit_cost(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10, unitCostMinor: 10000);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/ship-back")->assertOk();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/credit")
            ->assertOk()
            ->assertJsonPath('data.status', 'credited')
            // 4 units at 100.00 unit cost each.
            ->assertJsonPath('data.credit_amount', 400);

        $this->assertDatabaseHas('purchase_returns', ['id' => $returnId, 'status' => 'credited']);
    }

    public function test_crediting_a_purchase_return_can_override_the_suggested_amount(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10, unitCostMinor: 10000);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-returns/{$returnId}/ship-back")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-returns/{$returnId}/credit", ['credit_amount' => '350.00'])
            ->assertOk()
            ->assertJsonPath('data.credit_amount', 350);
    }

    public function test_purchase_order_show_response_includes_its_returns_summary(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item] = $this->receivedPurchaseOrder($store, quantityOrdered: 10, quantityReceived: 10);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/returns", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 2]],
            ])->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.returns')
            ->assertJsonPath('data.returns.0.status', 'requested');
    }

    public function test_a_user_without_purchase_returns_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/purchase-returns?store_id={$store->id}")
            ->assertForbidden();
    }
}
