<?php

namespace Tests\Feature\Order;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CouponTest extends TestCase
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

    /** @return array{store: Store, warehouse: Warehouse, customer: Customer, product: Product} */
    private function shoppableFixture(): array
    {
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50, 'quantity_reserved' => 0]);

        return compact('store', 'warehouse', 'customer', 'product');
    }

    private function placeOrder(User $admin, array $fixture, string $couponCode, int $quantity = 10, string $unitPrice = '200.00'): TestResponse
    {
        return $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $fixture['store']->id,
            'customer_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'payment_method' => 'cod',
            'coupon_code' => $couponCode,
            'items' => [
                ['product_id' => $fixture['product']->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
            ],
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
        ]);
    }

    public function test_a_percentage_coupon_computes_the_correct_discount_on_order_creation(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        $coupon = Coupon::factory()->for($fixture['store'])->create(['code' => 'SAVE10', 'percentage_value' => 10]);

        // Subtotal is 2000.00 -> 10% = 200.00 discount.
        $this->placeOrder($admin, $fixture, 'save10')
            ->assertCreated()
            ->assertJsonPath('data.subtotal_amount', 2000)
            ->assertJsonPath('data.discount_amount', 200)
            ->assertJsonPath('data.total_amount', 1800)
            ->assertJsonPath('data.coupon_code', 'SAVE10');

        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'discount_amount' => 20000]);
    }

    public function test_a_fixed_coupon_is_capped_at_the_subtotal(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        Coupon::factory()->for($fixture['store'])->fixed(500000)->create(['code' => 'HUGE']); // 5000.00, way above the 2000.00 subtotal

        $this->placeOrder($admin, $fixture, 'HUGE')
            ->assertCreated()
            ->assertJsonPath('data.discount_amount', 2000)
            ->assertJsonPath('data.total_amount', 0);
    }

    public function test_an_invalid_coupon_code_is_rejected_and_no_order_is_created(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();

        $this->placeOrder($admin, $fixture, 'DOES-NOT-EXIST')->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_coupon_below_its_minimum_order_amount_is_rejected(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        Coupon::factory()->for($fixture['store'])->create(['code' => 'BIGSPEND', 'minimum_order_amount' => 500000]);

        $this->placeOrder($admin, $fixture, 'BIGSPEND')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This coupon requires a minimum order of 5000 BDT.']);
    }

    public function test_a_coupon_past_its_usage_limit_is_rejected(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        Coupon::factory()->for($fixture['store'])->create(['code' => 'ONEUSE', 'usage_limit' => 1, 'used_count' => 1]);

        $this->placeOrder($admin, $fixture, 'ONEUSE')->assertStatus(422);
    }

    public function test_a_customer_past_their_per_customer_limit_is_rejected(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        $coupon = Coupon::factory()->for($fixture['store'])->create(['code' => 'ONCE', 'per_customer_limit' => 1]);

        $this->placeOrder($admin, $fixture, 'ONCE')->assertCreated();
        $this->assertSame(1, $coupon->fresh()->used_count);

        // Same customer, same coupon, second order.
        $this->placeOrder($admin, $fixture, 'ONCE')->assertStatus(422);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_an_inactive_coupon_is_rejected(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        Coupon::factory()->for($fixture['store'])->inactive()->create(['code' => 'OFF']);

        $this->placeOrder($admin, $fixture, 'OFF')->assertStatus(422);
    }

    public function test_cancelling_an_order_releases_its_coupon_usage(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        $coupon = Coupon::factory()->for($fixture['store'])->create(['code' => 'SAVE10', 'percentage_value' => 10]);

        $response = $this->placeOrder($admin, $fixture, 'SAVE10')->assertCreated();
        $orderId = $response->json('data.id');
        $this->assertSame(1, $coupon->fresh()->used_count);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/cancel")->assertOk();

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->assertDatabaseMissing('coupon_usages', ['order_id' => $orderId]);
    }

    public function test_editing_a_pending_order_to_drop_its_coupon_code_reverts_to_the_manual_discount(): void
    {
        $admin = $this->admin();
        $fixture = $this->shoppableFixture();
        $coupon = Coupon::factory()->for($fixture['store'])->create(['code' => 'SAVE10', 'percentage_value' => 10]);

        $response = $this->placeOrder($admin, $fixture, 'SAVE10')->assertCreated();
        $orderId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/orders/{$orderId}", [
            'store_id' => $fixture['store']->id,
            'customer_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'payment_method' => 'cod',
            'discount_amount' => '50.00',
            'items' => [
                ['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_price' => '200.00'],
            ],
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
        ])->assertOk()
            ->assertJsonPath('data.discount_amount', 50)
            ->assertJsonPath('data.coupon_code', null);

        $this->assertSame(0, $coupon->fresh()->used_count);
    }

    public function test_coupon_crud_is_gated_on_coupons_permissions(): void
    {
        $store = Store::factory()->create();
        $marketer = User::factory()->create();
        $marketer->assignRole('Marketing Manager');
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager'); // no coupons.* permissions at all

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/coupons?store_id='.$store->id)->assertForbidden();

        $create = $this->actingAs($marketer, 'sanctum')->postJson('/api/v1/coupons', [
            'store_id' => $store->id,
            'code' => 'welcome',
            'discount_type' => 'percentage',
            'percentage_value' => 15,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'WELCOME');

        $couponId = $create->json('data.id');

        $this->actingAs($viewer, 'sanctum')->putJson("/api/v1/coupons/{$couponId}", [
            'store_id' => $store->id,
            'code' => 'WELCOME',
            'discount_type' => 'percentage',
            'percentage_value' => 20,
        ])->assertForbidden();

        $this->actingAs($marketer, 'sanctum')->deleteJson("/api/v1/coupons/{$couponId}")->assertOk();
        $this->assertDatabaseMissing('coupons', ['id' => $couponId]);
    }

    public function test_a_duplicate_code_within_the_same_store_is_rejected(): void
    {
        $store = Store::factory()->create();
        $marketer = User::factory()->create();
        $marketer->assignRole('Marketing Manager');
        Coupon::factory()->for($store)->create(['code' => 'DUPE']);

        $this->actingAs($marketer, 'sanctum')->postJson('/api/v1/coupons', [
            'store_id' => $store->id,
            'code' => 'DUPE',
            'discount_type' => 'percentage',
            'percentage_value' => 10,
        ])->assertStatus(422);
    }
}
