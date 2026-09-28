<?php

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndGetToken(Store $store, string $phone = '01712345678'): string
    {
        return $this->postJson('/api/v1/account/auth/register', [
            'name' => 'Karim Rahman',
            'phone' => $phone,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->json('data.token');
    }

    public function test_a_customer_only_sees_their_own_orders(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->registerAndGetToken($store);
        $me = Customer::where('phone', '01712345678')->firstOrFail();
        $someoneElse = Customer::factory()->for($store)->create();

        $myOrder = Order::factory()->for($store)->for($me)->create();
        Order::factory()->for($store)->for($someoneElse)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/account/orders');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $myOrder->order_number);
    }

    public function test_a_customer_can_view_their_own_order_by_uuid_but_not_someone_elses(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->registerAndGetToken($store);
        $me = Customer::where('phone', '01712345678')->firstOrFail();
        $someoneElse = Customer::factory()->for($store)->create();

        $myOrder = Order::factory()->for($store)->for($me)->create();
        $otherOrder = Order::factory()->for($store)->for($someoneElse)->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/account/orders/{$myOrder->uuid}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $myOrder->order_number);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/account/orders/{$otherOrder->uuid}")
            ->assertStatus(404);
    }

    public function test_a_guest_checkout_placed_after_registration_with_the_same_phone_appears_in_the_account(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->registerAndGetToken($store);

        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        // Placed with NO Authorization header at all — a plain guest
        // checkout — but the same phone the account was registered with.
        $this->postJson('/api/v1/storefront/checkout', [
            'customer_name' => 'Karim Rahman',
            'customer_phone' => '01712345678',
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(1, Customer::count());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'storefront');
    }

    public function test_account_orders_require_authentication(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->getJson('/api/v1/account/orders')->assertStatus(401);
    }
}
