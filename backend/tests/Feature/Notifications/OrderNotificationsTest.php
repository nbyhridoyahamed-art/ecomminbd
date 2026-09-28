<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewOrderPlacedNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
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

    private function manualShipping(): array
    {
        return [
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
        ];
    }

    public function test_placing_an_admin_order_notifies_the_customer_but_not_staff(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50, 'quantity_reserved' => 0]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '100.00'],
            ],
            ...$this->manualShipping(),
        ])->assertCreated();

        Notification::assertSentTo($customer, OrderPlacedNotification::class);
        Notification::assertNotSentTo($admin, NewOrderPlacedNotification::class);
    }

    public function test_a_guest_checkout_notifies_both_the_customer_and_qualifying_staff(): void
    {
        Notification::fake();

        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $staff = User::factory()->create(['current_store_id' => $store->id, 'status' => 'active']);
        $staff->assignRole('Super Admin');

        $this->postJson('/api/v1/storefront/checkout', [
            'customer_name' => 'Karim Rahman',
            'customer_phone' => '01712345678',
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $customer = Customer::firstOrFail();
        $order = Order::firstOrFail();

        Notification::assertSentTo($customer, OrderPlacedNotification::class);
        Notification::assertSentTo($staff, NewOrderPlacedNotification::class, function ($notification) use ($order, $staff) {
            return $notification->toDatabase($staff)['order_id'] === $order->id;
        });
    }

    public function test_staff_without_orders_view_permission_or_in_a_different_store_are_not_notified(): void
    {
        Notification::fake();

        $store = Store::factory()->create(['status' => 'active']);
        $otherStore = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $noPermission = User::factory()->create(['current_store_id' => $store->id, 'status' => 'active']);
        $wrongStore = User::factory()->create(['current_store_id' => $otherStore->id, 'status' => 'active']);
        $wrongStore->assignRole('Super Admin');

        $this->postJson('/api/v1/storefront/checkout', [
            'customer_name' => 'Karim Rahman',
            'customer_phone' => '01712345678',
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        Notification::assertNotSentTo($noPermission, NewOrderPlacedNotification::class);
        Notification::assertNotSentTo($wrongStore, NewOrderPlacedNotification::class);
    }

    public function test_order_status_transitions_notify_the_customer(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50, 'quantity_reserved' => 0]);

        $orderId = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '100.00']],
            ...$this->manualShipping(),
        ])->json('data.id');

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/process")->assertOk();
        Notification::assertSentTo($customer, OrderStatusChangedNotification::class, fn ($n) => $n->toSms($customer) === "Order {$this->orderNumber($orderId)} is now being processed.");

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/ship")->assertOk();
        Notification::assertSentTo($customer, OrderStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been shipped'));

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/deliver")->assertOk();
        Notification::assertSentTo($customer, OrderStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been delivered'));
    }

    public function test_cancelling_an_order_notifies_the_customer(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50, 'quantity_reserved' => 0]);

        $orderId = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '100.00']],
            ...$this->manualShipping(),
        ])->json('data.id');

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/cancel")->assertOk();

        Notification::assertSentTo($customer, OrderStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been cancelled'));
    }

    public function test_a_customer_with_no_email_only_gets_the_sms_channel(): void
    {
        Notification::fake();

        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', [
            'customer_name' => 'Karim Rahman',
            'customer_phone' => '01712345678',
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $customer = Customer::firstOrFail();
        $this->assertNull($customer->email);

        Notification::assertSentTo($customer, OrderPlacedNotification::class, function ($notification, array $channels) {
            return ! in_array('mail', $channels, true);
        });
    }

    private function orderNumber(int $orderId): string
    {
        return Order::findOrFail($orderId)->order_number;
    }
}
