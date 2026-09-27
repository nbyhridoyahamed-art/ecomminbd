<?php

namespace Tests\Feature\Returns;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
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

    public function test_a_partial_return_refund_does_not_flip_the_orders_payment_status(): void
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

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
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
