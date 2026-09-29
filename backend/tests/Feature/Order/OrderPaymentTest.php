<?php

namespace Tests\Feature\Order;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
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

    /** An order with two items totaling 2000.00 (10 x 199.00 + 1 x 10.00), no shipping/discount. */
    private function orderTotaling2000(): Order
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'bkash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => '200.00'],
            ],
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
        ])->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    public function test_recording_a_payment_marks_the_order_partially_paid_then_paid(): void
    {
        $order = $this->orderTotaling2000();
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '1200.00',
            'method' => 'bkash',
            'reference' => 'TXN-001',
        ])->assertCreated()
            ->assertJsonPath('data.payment_status', 'partially_paid');

        $this->assertSame('partially_paid', $order->fresh()->payment_status);

        $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '800.00',
            'method' => 'bkash',
        ])->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_a_cancelled_order_cannot_receive_a_payment(): void
    {
        $order = $this->orderTotaling2000();
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');
        $order->update(['status' => 'cancelled']);

        $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '500.00',
            'method' => 'bkash',
        ])->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_only_a_user_with_orders_record_payment_can_record_a_payment(): void
    {
        $order = $this->orderTotaling2000();

        $orderManager = User::factory()->create();
        $orderManager->assignRole('Order Manager');

        $this->actingAs($orderManager, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '500.00',
            'method' => 'bkash',
        ])->assertForbidden();

        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '500.00',
            'method' => 'bkash',
        ])->assertCreated();
    }

    public function test_the_order_show_response_lists_its_payments(): void
    {
        $order = $this->orderTotaling2000();
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/orders/{$order->id}/payments", [
            'amount' => '1200.00',
            'method' => 'nagad',
            'reference' => 'TXN-42',
        ])->assertCreated();

        $this->actingAs($accountant, 'sanctum')->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payments.0.amount', 1200)
            ->assertJsonPath('data.payments.0.method', 'nagad')
            ->assertJsonPath('data.payments.0.reference', 'TXN-42')
            ->assertJsonPath('data.payment_status', 'partially_paid');
    }
}
