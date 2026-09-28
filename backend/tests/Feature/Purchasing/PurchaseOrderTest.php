<?php

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
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

    public function test_a_line_item_can_target_a_specific_variant(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        ['product' => $product, 'variant' => $variant] = $this->variantProduct($store);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/purchase-orders', [
            'store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity_ordered' => 10, 'unit_cost' => '150.50'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.product_variant.id', $variant->id)
            ->assertJsonPath('data.items.0.product_variant.sku', $variant->sku);

        $this->assertDatabaseHas('purchase_order_items', ['product_id' => $product->id, 'product_variant_id' => $variant->id]);
    }

    public function test_a_variant_that_does_not_belong_to_the_selected_product_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        ['product' => $product] = $this->variantProduct($store);
        ['variant' => $otherProductsVariant] = $this->variantProduct($store);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/purchase-orders', [
            'store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'product_variant_id' => $otherProductsVariant->id, 'quantity_ordered' => 10, 'unit_cost' => '150.50'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_variant_id');
    }

    public function test_a_draft_purchase_order_can_be_created_with_items_and_money_converts_correctly(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/purchase-orders', [
            'store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => '150.50'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.0.unit_cost', 150.5)
            ->assertJsonPath('data.items.0.quantity_remaining', 10)
            ->assertJsonPath('data.total_amount', 1505);

        $this->assertDatabaseHas('purchase_order_items', ['product_id' => $product->id, 'unit_cost_amount' => 15050]);
    }

    public function test_draft_items_can_be_replaced_wholesale_via_update(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $productA = Product::factory()->for($store)->create();
        $productB = Product::factory()->for($store)->create();

        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $order->items()->create(['product_id' => $productA->id, 'quantity_ordered' => 5, 'unit_cost_amount' => 1000]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/purchase-orders/{$order->id}", [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'items' => [
                    ['product_id' => $productB->id, 'quantity_ordered' => 20, 'unit_cost' => '10.00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $productB->id);

        $this->assertDatabaseMissing('purchase_order_items', ['product_id' => $productA->id]);
    }

    public function test_placing_an_order_requires_at_least_one_item_and_locks_it_from_editing(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();

        $empty = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$empty->id}/place")
            ->assertStatus(422);

        $product = Product::factory()->for($store)->create();
        $order = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $order->items()->create(['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost_amount' => 1000]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$order->id}/place")
            ->assertOk()
            ->assertJsonPath('data.status', 'ordered')
            // Regression: place()'s response must include `receipts` (even
            // empty) like show() does — a frontend that caches this
            // response in place of a show() response must not lose the
            // field (found via e2e: the show page crashed reading
            // `order.receipts.length` after place()).
            ->assertJsonPath('data.receipts', []);

        // Now locked: editing an ordered PO is rejected.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/purchase-orders/{$order->id}", [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 99, 'unit_cost' => '1.00'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_an_order_can_be_cancelled_from_draft_or_ordered_but_not_after(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();

        $draft = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$draft->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.receipts', []);

        $received = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'received']);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/purchase-orders/{$received->id}/cancel")
            ->assertStatus(422);
    }

    public function test_only_draft_orders_can_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();

        $draft = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/purchase-orders/{$draft->id}")->assertOk();

        $ordered = PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'ordered']);
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/purchase-orders/{$ordered->id}")->assertStatus(422);
    }

    public function test_duplicate_products_in_items_are_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/purchase-orders', [
                'store_id' => $store->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity_ordered' => 5, 'unit_cost' => '10.00'],
                    ['product_id' => $product->id, 'quantity_ordered' => 3, 'unit_cost' => '10.00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_a_user_without_purchase_orders_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/purchase-orders')
            ->assertForbidden();
    }

    public function test_the_open_filter_excludes_received_and_cancelled_orders(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $supplier = Supplier::factory()->for($store)->create();

        PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'draft']);
        PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'ordered']);
        PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'partially_received']);
        PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'received']);
        PurchaseOrder::factory()->for($store)->for($warehouse)->for($supplier)->create(['status' => 'cancelled']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/purchase-orders?store_id={$store->id}&open=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}
