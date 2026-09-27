<?php

namespace Tests\Feature\Delivery;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
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
