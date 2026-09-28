<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ReturnStatusChangedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReturnNotificationsTest extends TestCase
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

    /** @return array{order: Order, item: OrderItem, customer: Customer} */
    private function deliveredOrder(Store $store): array
    {
        $customer = Customer::factory()->for($store)->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create([
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'shipping_amount' => 0,
            'discount_amount' => 0,
        ]);

        $item = $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price_amount' => 10000]);

        return compact('order', 'item', 'customer');
    }

    private function requestReturn(User $admin, Order $order, OrderItem $item, int $quantity = 1): int
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/returns", [
                'items' => [['order_item_id' => $item->id, 'quantity' => $quantity]],
            ])
            ->json('data.id');
    }

    public function test_approving_a_return_notifies_the_customer(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'customer' => $customer] = $this->deliveredOrder($store);
        $returnId = $this->requestReturn($admin, $order, $item);

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();

        Notification::assertSentTo($customer, ReturnStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been approved'));
    }

    public function test_rejecting_a_return_notifies_the_customer(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'customer' => $customer] = $this->deliveredOrder($store);
        $returnId = $this->requestReturn($admin, $order, $item);

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/reject")->assertOk();

        Notification::assertSentTo($customer, ReturnStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been rejected'));
    }

    public function test_receiving_a_return_notifies_the_customer(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'customer' => $customer] = $this->deliveredOrder($store);
        $returnId = $this->requestReturn($admin, $order, $item);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        Notification::assertSentTo($customer, ReturnStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been received'));
    }

    public function test_refunding_a_return_notifies_the_customer(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['order' => $order, 'item' => $item, 'customer' => $customer] = $this->deliveredOrder($store);
        $returnId = $this->requestReturn($admin, $order, $item);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive")->assertOk();

        Notification::fake();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/returns/{$returnId}/refund")->assertOk();

        Notification::assertSentTo($customer, ReturnStatusChangedNotification::class, fn ($n) => str_contains($n->toSms($customer), 'has been refunded'));
    }
}
