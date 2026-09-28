<?php

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiptTest extends TestCase
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

    private function orderedWithItem(int $quantityOrdered = 20): array
    {
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->ordered()->create();
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity_ordered' => $quantityOrdered,
            'unit_cost_amount' => 1000,
        ]);

        return compact('store', 'warehouse', 'supplier', 'product', 'order', 'item');
    }

    private function orderedWithVariantItem(int $quantityOrdered = 20): array
    {
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
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

        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->ordered()->create();
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_ordered' => $quantityOrdered,
            'unit_cost_amount' => 1000,
        ]);

        return compact('store', 'warehouse', 'supplier', 'product', 'variant', 'order', 'item');
    }

    public function test_a_partial_receipt_moves_stock_and_marks_the_order_partially_received(): void
    {
        $admin = $this->admin();
        ['warehouse' => $warehouse, 'product' => $product, 'order' => $order, 'item' => $item] = $this->orderedWithItem(20);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
                'items' => [
                    ['purchase_order_item_id' => $item->id, 'quantity_received' => 8],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity_received', 8);

        $order->refresh();
        $this->assertSame('partially_received', $order->status);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_received' => 8]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 8]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'purchase_receipt', 'quantity' => 8,
        ]);
    }

    public function test_receiving_against_a_variant_line_increases_only_that_variants_stock(): void
    {
        $admin = $this->admin();
        ['warehouse' => $warehouse, 'product' => $product, 'variant' => $variant, 'order' => $order, 'item' => $item] =
            $this->orderedWithVariantItem(20);

        // A decoy variant-less row for the same product — receiving against
        // the variant line must never touch this one.
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 999]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
                'items' => [
                    ['purchase_order_item_id' => $item->id, 'quantity_received' => 8],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_sku', $variant->sku);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 8,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id, 'quantity' => 999,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id,
            'type' => 'purchase_receipt', 'quantity' => 8,
        ]);
    }

    public function test_receiving_the_remaining_quantity_marks_the_order_fully_received(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'item' => $item] = $this->orderedWithItem(20);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 8]],
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 12]],
        ])->assertCreated();

        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_received' => 20]);
    }

    public function test_over_receiving_beyond_the_remaining_quantity_is_rejected(): void
    {
        $admin = $this->admin();
        ['order' => $order, 'item' => $item] = $this->orderedWithItem(20);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 25]],
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_received' => 0]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_draft_order_cannot_receive_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $draft = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $item = $draft->items()->create(['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost_amount' => 500]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$draft->id}/receipts", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_a_user_without_purchase_orders_receive_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        ['order' => $order, 'item' => $item] = $this->orderedWithItem(20);

        $this->actingAs($viewer, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/receipts", [
                'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 1]],
            ])
            ->assertForbidden();
    }
}
