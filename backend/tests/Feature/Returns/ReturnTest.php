<?php

namespace Tests\Feature\Returns;

use App\Models\BundleItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnTest extends TestCase
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
     * A delivered order with two items: A (qty 3 @ 100.00 = 300.00) and
     * B (qty 2 @ 50.00 = 100.00) — order total 400.00.
     *
     * @return array{order: Order, itemA: OrderItem, itemB: OrderItem, productA: Product, productB: Product}
     */
    private function deliveredOrder(Store $store, string $paymentMethod = 'cod'): array
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $productA = Product::factory()->for($store)->create();
        $productB = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'delivered',
            'payment_method' => $paymentMethod,
            'payment_status' => 'paid',
            'shipping_amount' => 0,
            'discount_amount' => 0,
        ]);

        $itemA = $order->items()->create(['product_id' => $productA->id, 'quantity' => 3, 'unit_price_amount' => 10000]);
        $itemB = $order->items()->create(['product_id' => $productB->id, 'quantity' => 2, 'unit_price_amount' => 5000]);

        return compact('order', 'itemA', 'itemB', 'productA', 'productB');
    }

    /** @return array{order: Order, item: OrderItem, product: Product, variant: ProductVariant, warehouse: Warehouse} */
    private function deliveredOrderWithVariantItem(Store $store): array
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
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

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_amount' => 0,
            'discount_amount' => 0,
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price_amount' => 10000,
        ]);

        return compact('order', 'item', 'product', 'variant', 'warehouse');
    }

    /**
     * A delivered order with one bundle line item (2x widget + 1x gadget per
     * bundle unit). Built the same way deliveredOrder() is — via a direct
     * items()->create() call that bypasses OrderController::syncItems() — so
     * no order_item_components snapshot rows exist and OrderItem::resolvedComponents()
     * must fall back to a live BundleExpander::expand() call.
     *
     * @return array{order: Order, item: OrderItem, bundle: Product, widget: Product, gadget: Product, warehouse: Warehouse}
     */
    private function deliveredOrderWithBundleItem(Store $store, int $bundleQuantity = 3): array
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'name' => 'Combo Pack']);
        $widget = Product::factory()->for($store)->create(['name' => 'Widget']);
        $gadget = Product::factory()->for($store)->create(['name' => 'Gadget']);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $widget->id, 'quantity' => 2]);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $gadget->id, 'quantity' => 1]);

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_amount' => 0,
            'discount_amount' => 0,
        ]);

        $item = $order->items()->create(['product_id' => $bundle->id, 'quantity' => $bundleQuantity, 'unit_price_amount' => 100000]);

        return compact('order', 'item', 'bundle', 'widget', 'gadget', 'warehouse');
    }

    public function test_receiving_a_full_bundle_return_restocks_each_components_full_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'widget' => $widget, 'gadget' => $gadget] =
            $this->deliveredOrderWithBundleItem($store, bundleQuantity: 3);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $item->id, 'quantity' => 3]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        // 3 bundle units need 3*2=6 widgets and 3*1=3 gadgets restocked.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $widget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 6]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $gadget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 3]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $widget->id, 'type' => 'return', 'quantity' => 6,
            'reference_type' => OrderReturn::class, 'reference_id' => $returnId,
        ]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $gadget->id, 'type' => 'return', 'quantity' => 3]);
    }

    public function test_a_partial_bundle_return_restocks_only_the_prorated_component_quantities(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'widget' => $widget, 'gadget' => $gadget] =
            $this->deliveredOrderWithBundleItem($store, bundleQuantity: 3);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        // Only 1 of the 3 bundle units returned -> 1*2=2 widgets and 1*1=1
        // gadget restocked, not the whole line's component quantities.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $widget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 2]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $gadget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 1]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $widget->id, 'type' => 'return', 'quantity' => 2]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $gadget->id, 'type' => 'return', 'quantity' => 1]);
    }

    public function test_receiving_a_variant_return_restocks_only_that_variants_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'product' => $product, 'variant' => $variant, 'warehouse' => $warehouse] =
            $this->deliveredOrderWithVariantItem($store);

        // A decoy variant-less row for the same product — receiving the
        // return must never touch this one.
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 999]);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 2,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id, 'quantity' => 999,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'type' => 'return', 'quantity' => 2,
        ]);
    }

    public function test_a_return_can_be_requested_for_a_delivered_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'reason' => 'Wrong size',
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('return_status_history', ['to_status' => 'requested', 'from_status' => null]);
    }

    public function test_a_return_cannot_be_requested_for_a_non_delivered_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);
        $order->update(['status' => 'shipped']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_a_return_cannot_request_more_than_the_ordered_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 4]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_an_open_return_reserves_the_quantity_but_a_rejected_one_frees_it_back_up(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $firstReturnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 2]],
            ])
            ->assertCreated()->json('data.id');

        // Only 1 of the 3 ordered units remains eligible while the first
        // return is still open (requested).
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 2]],
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$firstReturnId}/reject")->assertOk();

        // Rejecting the first return frees its quantity back up.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 2]],
            ])
            ->assertCreated();
    }

    public function test_full_lifecycle_receiving_restocks_and_a_full_order_refund_reconciles_payment_status(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA, 'itemB' => $itemB, 'productA' => $productA, 'productB' => $productB] =
            $this->deliveredOrder($store);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'reason' => 'Changed my mind',
                'items' => [
                    ['order_item_id' => $itemA->id, 'quantity' => 3],
                    ['order_item_id' => $itemB->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")
            ->assertOk()->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('stock_levels', ['product_id' => $productA->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 3]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $productB->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 2]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id, 'type' => 'return', 'quantity' => 3,
            'reference_type' => OrderReturn::class, 'reference_id' => $returnId,
        ]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $productB->id, 'type' => 'return', 'quantity' => 2]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/refund")
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refund_amount', 400);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded']);
    }

    public function test_a_partial_return_refund_flips_the_orders_payment_status_to_partially_refunded(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 1]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/refund")
            ->assertOk()
            ->assertJsonPath('data.refund_amount', 100);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'partially_refunded']);
    }

    public function test_payment_status_becomes_refunded_only_once_every_item_is_covered_across_multiple_returns(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA, 'itemB' => $itemB] = $this->deliveredOrder($store);

        // First return: only part of item A (1 of 3) — nowhere near full coverage.
        $firstReturnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['order_item_id' => $itemA->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$firstReturnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$firstReturnId}/receive")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$firstReturnId}/refund")->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'partially_refunded']);

        // Second return: the rest of item A plus all of item B — every item
        // is now fully covered, but only by combining two separate returns.
        $secondReturnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [
                    ['order_item_id' => $itemA->id, 'quantity' => 2],
                    ['order_item_id' => $itemB->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$secondReturnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$secondReturnId}/receive")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$secondReturnId}/refund")->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded']);
    }

    public function test_a_refund_via_store_credit_issues_a_ledger_entry_and_is_visible_on_the_customers_ledger(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['order_item_id' => $itemA->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/returns/{$returnId}/refund", ['refund_method' => 'store_credit'])
            ->assertOk()
            ->assertJsonPath('data.refund_method', 'store_credit')
            ->assertJsonPath('data.refund_amount', 100);

        // A store-credit refund still reconciles payment_status the same as
        // any other refund method — it's a different settlement, not a
        // different notion of "has this order been refunded".
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'partially_refunded']);
        $this->assertDatabaseHas('customer_store_credits', [
            'customer_id' => $order->customer_id, 'amount' => 10000,
            'reference_type' => OrderReturn::class, 'reference_id' => $returnId,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/customers/{$order->customer_id}/store-credits")
            ->assertOk()
            ->assertJsonPath('meta.balance', 100)
            ->assertJsonPath('data.0.type', 'issued')
            ->assertJsonPath('data.0.amount', 100);
    }

    public function test_an_exchange_item_creates_a_replacement_order_and_reserves_stock_for_it(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);
        $newProduct = Product::factory()->for($store)->create();
        StockLevel::create(['product_id' => $newProduct->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 20, 'quantity_reserved' => 0]);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 1, 'exchange_product_id' => $newProduct->id]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.exchange_product_id', $newProduct->id)
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();
        $replacementOrderId = $response->json('data.replacement_order.id');
        $this->assertNotNull($replacementOrderId);

        $this->assertDatabaseHas('orders', [
            'id' => $replacementOrderId, 'customer_id' => $order->customer_id,
            'status' => 'pending', 'payment_status' => 'paid', 'store_credit_amount' => 0,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $replacementOrderId, 'product_id' => $newProduct->id, 'quantity' => 1, 'unit_price_amount' => 0,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $newProduct->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 20, 'quantity_reserved' => 1,
        ]);
        // The old item was also restocked (restock defaults true) — exchange
        // and restock are independent, both can happen for the same item.
        $this->assertDatabaseHas('stock_movements', ['product_id' => $itemA->product_id, 'type' => 'return', 'quantity' => 1]);
    }

    public function test_receive_rolls_back_entirely_when_the_exchange_product_has_insufficient_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);
        $newProduct = Product::factory()->for($store)->create();
        StockLevel::create(['product_id' => $newProduct->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 0, 'quantity_reserved' => 0]);

        $returnId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 1, 'exchange_product_id' => $newProduct->id]],
            ])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertStatus(422);

        // The whole receive() is one transaction — the original item's
        // restock (which would have succeeded on its own) must not have
        // applied either.
        $this->assertDatabaseHas('returns', ['id' => $returnId, 'status' => 'approved']);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $itemA->product_id, 'type' => 'return']);
    }

    public function test_receive_can_override_the_restock_decision_per_item(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA, 'productA' => $productA] = $this->deliveredOrder($store);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $itemA->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $returnId = $response->json('data.id');
        $returnItemId = $response->json('data.items.0.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();

        // Damaged on arrival — mark it not restockable at receive time.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/returns/{$returnId}/receive", [
                'items' => [['return_item_id' => $returnItemId, 'restock' => false]],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('stock_movements', ['product_id' => $productA->id, 'type' => 'return']);
        $this->assertDatabaseMissing('stock_levels', ['product_id' => $productA->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 1]);
    }

    public function test_a_return_can_be_rejected_from_requested_or_approved_but_not_after(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'itemA' => $itemA] = $this->deliveredOrder($store);

        $requestedId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['order_item_id' => $itemA->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$requestedId}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $approvedId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", ['items' => [['order_item_id' => $itemA->id, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$approvedId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$approvedId}/reject")
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$approvedId}/receive")->assertStatus(422);
    }

    public function test_a_user_without_returns_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Inventory Manager');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/returns?store_id={$store->id}")
            ->assertForbidden();
    }
}
