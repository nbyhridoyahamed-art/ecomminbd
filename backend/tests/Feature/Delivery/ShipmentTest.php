<?php

namespace Tests\Feature\Delivery;

use App\Models\BundleItem;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTest extends TestCase
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

    /** A shipped order with one item, subtotal 200.00 + shipping 50.00 = total 250.00. */
    private function shippedOrder(Store $store, string $paymentMethod = 'cod'): Order
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'shipped',
            'payment_method' => $paymentMethod,
            'payment_status' => 'unpaid',
            'shipping_amount' => 5000,
            'discount_amount' => 0,
        ]);

        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price_amount' => 10000]);

        return $order;
    }

    /** @return array{order: Order, product: Product, variant: ProductVariant} */
    private function shippedOrderWithVariant(Store $store): array
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
            'status' => 'shipped',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_amount' => 5000,
            'discount_amount' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price_amount' => 10000,
        ]);

        return compact('order', 'product', 'variant');
    }

    /**
     * A shipped order with one bundle line item (2x widget + 1x gadget per
     * bundle unit), built via a direct items()->create() call the same way
     * shippedOrder()/shippedOrderWithVariant() are — so resolvedComponents()
     * must fall back to a live BundleExpander::expand() call, since no
     * order_item_components snapshot exists.
     *
     * @return array{order: Order, bundle: Product, widget: Product, gadget: Product}
     */
    private function shippedOrderWithBundleItem(Store $store, int $bundleQuantity = 2): array
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'name' => 'Combo Pack']);
        $widget = Product::factory()->for($store)->create(['name' => 'Widget']);
        $gadget = Product::factory()->for($store)->create(['name' => 'Gadget']);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $widget->id, 'quantity' => 2]);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $gadget->id, 'quantity' => 1]);

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'shipped',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_amount' => 5000,
            'discount_amount' => 0,
        ]);

        $order->items()->create(['product_id' => $bundle->id, 'quantity' => $bundleQuantity, 'unit_price_amount' => 100000]);

        return compact('order', 'bundle', 'widget', 'gadget');
    }

    public function test_a_failed_bundle_delivery_marked_returned_to_seller_restocks_each_components_full_quantity(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'widget' => $widget, 'gadget' => $gadget] = $this->shippedOrderWithBundleItem($store, bundleQuantity: 2);
        $courier = Courier::factory()->for($store)->create();

        // Simulate the on-hand quantity Order.ship() already decremented for
        // each component before this shipment ever existed.
        StockLevel::factory()->for($widget)->for($order->warehouse)->create(['quantity' => 8]);
        StockLevel::factory()->for($gadget)->for($order->warehouse)->create(['quantity' => 5]);

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-BUNDLE-1'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/failed", ['note' => 'Customer not available'])
            ->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/returned")->assertOk();

        // The whole shipment failed (not a partial return), so each
        // component's full snapshotted quantity is restocked: 2 bundle units
        // -> 2*2=4 widgets, 2*1=2 gadgets.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $widget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 12]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $gadget->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 7]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $widget->id, 'type' => 'return', 'quantity' => 4,
            'quantity_before' => 8, 'quantity_after' => 12, 'reference_type' => Shipment::class, 'reference_id' => $shipmentId,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $gadget->id, 'type' => 'return', 'quantity' => 2,
            'quantity_before' => 5, 'quantity_after' => 7, 'reference_type' => Shipment::class, 'reference_id' => $shipmentId,
        ]);
    }

    public function test_returning_a_failed_variant_delivery_restocks_only_that_variants_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'product' => $product, 'variant' => $variant] = $this->shippedOrderWithVariant($store);
        $courier = Courier::factory()->for($store)->create();

        StockLevel::create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 8,
        ]);
        // A decoy variant-less row for the same product — restocking must
        // never touch this one.
        StockLevel::factory()->for($product)->for($order->warehouse)->create(['quantity' => 999]);

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-VAR-1'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/failed", ['note' => 'Customer not available'])
            ->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/returned")->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $order->warehouse_id, 'quantity' => 999,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'type' => 'return', 'quantity' => 2,
            'reference_type' => Shipment::class, 'reference_id' => $shipmentId,
        ]);
    }

    public function test_a_shipment_can_be_created_for_a_shipped_order_and_defaults_delivery_charge_to_shipping_amount(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $courier = Courier::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", [
                'courier_id' => $courier->id,
                'tracking_number' => 'TRK-TEST-001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_pickup')
            ->assertJsonPath('data.delivery_charge', 50);

        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'delivery_charge_amount' => 5000]);
    }

    public function test_a_shipment_cannot_be_created_for_a_non_shipped_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => 'pending']);
        $courier = Courier::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", [
                'courier_id' => $courier->id,
                'tracking_number' => 'TRK-TEST-002',
            ])
            ->assertStatus(422);
    }

    public function test_an_order_cannot_have_two_shipments(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $courier = Courier::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-1'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-2'])
            ->assertStatus(422);
    }

    public function test_re_dispatching_after_a_failed_delivery_that_never_returned_does_not_touch_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $product = $order->items->first()->product;
        $courier = Courier::factory()->for($store)->create();

        StockLevel::factory()->for($product)->for($order->warehouse)->create(['quantity' => 10]);

        $shipment1Id = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-RD-1'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/failed")->assertOk();
        // Deliberately never calling /returned — the courier still has the parcel.

        $shipment2Id = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-RD-2'])
            ->assertCreated()->json('data.id');

        // Nothing was ever restocked, so re-dispatching needs no stock change.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 10]);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => Shipment::class, 'reference_id' => $shipment2Id]);
    }

    public function test_re_dispatching_after_returned_to_seller_re_decrements_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $product = $order->items->first()->product;
        $courier = Courier::factory()->for($store)->create();

        StockLevel::factory()->for($product)->for($order->warehouse)->create(['quantity' => 10]);

        $shipment1Id = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-RS-1'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/failed")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/returned")->assertOk();

        // returned() restocked the full 2 units: 10 + 2 = 12.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 12]);

        $shipment2Id = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-RS-2'])
            ->assertCreated()->json('data.id');

        // Re-dispatching sends the same goods back out -> decremented again: 12 - 2 = 10.
        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 10]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'sale', 'quantity' => 2,
            'quantity_before' => 12, 'quantity_after' => 10,
            'reference_type' => Shipment::class, 'reference_id' => $shipment2Id,
        ]);
    }

    public function test_a_re_dispatch_is_rejected_and_rolled_back_when_stock_is_no_longer_sufficient(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $product = $order->items->first()->product;
        $courier = Courier::factory()->for($store)->create();

        StockLevel::factory()->for($product)->for($order->warehouse)->create(['quantity' => 2]);

        $shipment1Id = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-INS-1'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/failed")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipment1Id}/returned")->assertOk();

        // Someone else adjusted the restocked units away before re-dispatch.
        StockLevel::query()->where('product_id', $product->id)->where('warehouse_id', $order->warehouse_id)->update(['quantity' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-INS-2'])
            ->assertStatus(422);

        // The transaction rolled back — no second shipment row was left behind.
        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_full_status_progression_for_a_non_cod_order_does_not_touch_payment_status(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store, 'bkash');
        $courier = Courier::factory()->for($store)->create();

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-3'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")
            ->assertOk()->assertJsonPath('data.status', 'picked_up');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/in-transit")
            ->assertOk()->assertJsonPath('data.status', 'in_transit');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.cod_amount_collected', null);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'unpaid']);
    }

    public function test_delivering_a_cod_shipment_captures_the_order_total_and_marks_it_paid(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store, 'cod');
        $courier = Courier::factory()->for($store)->create();

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-4'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/in-transit")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/delivered")
            ->assertOk()
            ->assertJsonPath('data.cod_amount_collected', 250);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'payment_status' => 'paid']);
    }

    public function test_delivering_a_cod_shipment_can_override_the_collected_amount(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store, 'cod');
        $courier = Courier::factory()->for($store)->create();

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-5'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/delivered", ['cod_amount_collected' => '200.00'])
            ->assertOk()
            ->assertJsonPath('data.cod_amount_collected', 200);
    }

    public function test_a_failed_delivery_can_be_marked_returned_to_seller_without_delivering_the_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $courier = Courier::factory()->for($store)->create();

        // Simulate the on-hand quantity Order.ship() already decremented
        // before this shipment ever existed (the order has 2 units of its
        // one item — see shippedOrder()).
        $product = $order->items->first()->product;
        StockLevel::factory()->for($product)->for($order->warehouse)->create(['quantity' => 8]);

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-6'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/picked-up")->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/failed", ['note' => 'Customer not available'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed_delivery');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/shipments/{$shipmentId}/returned")
            ->assertOk()
            ->assertJsonPath('data.status', 'returned_to_seller');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);

        // The 2 units never reached the customer and are physically back —
        // on-hand quantity must be restored, with a real 'return' movement
        // recording it (this is the Phase 9 gap Phase 10 closes).
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $order->warehouse_id, 'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'return', 'quantity' => 2,
            'quantity_before' => 8, 'quantity_after' => 10,
            'reference_type' => Shipment::class, 'reference_id' => $shipmentId,
        ]);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $courier = Courier::factory()->for($store)->create();

        $shipmentId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-7'])
            ->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/delivered")->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/returned")->assertStatus(422);
    }

    public function test_marking_an_order_delivered_directly_is_blocked_once_it_has_a_shipment(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $order = $this->shippedOrder($store);
        $courier = Courier::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/shipments", ['courier_id' => $courier->id, 'tracking_number' => 'TRK-8'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/deliver")
            ->assertStatus(422);
    }

    public function test_a_user_without_shipments_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/shipments?store_id={$store->id}")
            ->assertForbidden();
    }
}
