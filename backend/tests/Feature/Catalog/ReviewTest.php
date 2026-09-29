<?php

namespace Tests\Feature\Catalog;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(Store $store): User
    {
        $user = User::factory()->create(['current_store_id' => $store->id]);
        $user->assignRole('Super Admin');

        return $user;
    }

    private function customerToken(Store $store, string $phone = '01712345678'): string
    {
        return $this->postJson('/api/v1/account/auth/register', [
            'name' => 'Karim Rahman',
            'phone' => $phone,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->json('data.token');
    }

    /** A delivered order for the given customer containing the given product. */
    private function deliveredOrder(Store $store, Customer $customer, Product $product): Order
    {
        $order = Order::factory()->for($store)->for($customer)->create(['status' => 'delivered']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price_amount' => $product->price_amount]);

        return $order;
    }

    public function test_a_customer_can_review_a_product_from_a_delivered_order(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $customer = Customer::where('phone', '01712345678')->firstOrFail();
        $product = Product::factory()->for($store)->create();
        $this->deliveredOrder($store, $customer, $product);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/account/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Great product',
            'body' => 'Worked exactly as described.',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rating', 5);
        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    public function test_a_customer_cannot_review_a_product_without_a_delivered_order(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $product = Product::factory()->for($store)->create();

        // No order at all for this product.
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/account/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'body' => 'Never received this but reviewing anyway.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_customer_cannot_review_a_product_from_an_order_that_is_not_yet_delivered(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $customer = Customer::where('phone', '01712345678')->firstOrFail();
        $product = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->create(['status' => 'processing']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price_amount' => $product->price_amount]);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/account/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
            'body' => 'Still in transit.',
        ])->assertStatus(422);
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $customer = Customer::where('phone', '01712345678')->firstOrFail();
        $product = Product::factory()->for($store)->create();
        $this->deliveredOrder($store, $customer, $product);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/account/reviews', [
            'product_id' => $product->id, 'rating' => 5, 'body' => 'First review.',
        ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/account/reviews', [
            'product_id' => $product->id, 'rating' => 1, 'body' => 'Trying again.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_a_customer_sees_their_own_reviews_regardless_of_status(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $customer = Customer::where('phone', '01712345678')->firstOrFail();
        $product = Product::factory()->for($store)->create();

        Review::factory()->for($store)->for($product)->for($customer)->create(['status' => 'pending']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_the_storefront_only_shows_approved_reviews(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        Review::factory()->for($store)->for($product)->approved()->create(['title' => 'Visible review']);
        Review::factory()->for($store)->for($product)->create(['status' => 'pending', 'title' => 'Hidden pending review']);
        Review::factory()->for($store)->for($product)->rejected()->create(['title' => 'Hidden rejected review']);

        $response = $this->getJson("/api/v1/storefront/products/{$product->slug}/reviews");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Visible review');
    }

    public function test_the_storefront_product_exposes_average_rating_and_reviews_count(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        Review::factory()->for($store)->for($product)->approved()->create(['rating' => 4]);
        Review::factory()->for($store)->for($product)->approved()->create(['rating' => 3]);
        Review::factory()->for($store)->for($product)->create(['status' => 'pending', 'rating' => 5]);

        $response = $this->getJson("/api/v1/storefront/products/{$product->slug}");

        // 3.5, not a whole number — sidesteps PHP's json_encode dropping a
        // float's trailing ".0" (3.0 would otherwise wire-encode as the
        // JSON integer 3, failing a strict assertJsonPath comparison
        // despite the average being computed correctly either way).
        $response->assertOk()
            ->assertJsonPath('data.reviews_count', 2)
            ->assertJsonPath('data.average_rating', 3.5);
    }

    public function test_admin_can_moderate_reviews(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $admin = $this->admin($store);
        $product = Product::factory()->for($store)->create();
        $review = Review::factory()->for($store)->for($product)->create(['status' => 'pending']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'approved']);
    }

    public function test_the_account_order_detail_flags_which_items_are_still_reviewable(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $token = $this->customerToken($store);
        $customer = Customer::where('phone', '01712345678')->firstOrFail();
        $reviewedProduct = Product::factory()->for($store)->create();
        $freshProduct = Product::factory()->for($store)->create();

        $order = Order::factory()->for($store)->for($customer)->create(['status' => 'delivered']);
        $order->items()->create(['product_id' => $reviewedProduct->id, 'quantity' => 1, 'unit_price_amount' => 100]);
        $order->items()->create(['product_id' => $freshProduct->id, 'quantity' => 1, 'unit_price_amount' => 100]);

        Review::factory()->for($store)->for($reviewedProduct)->for($customer)->for($order)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/account/orders/{$order->uuid}");

        $items = collect($response->json('data.items'));
        $this->assertFalse($items->firstWhere('product_id', $reviewedProduct->id)['reviewable']);
        $this->assertTrue($items->firstWhere('product_id', $freshProduct->id)['reviewable']);
    }

    public function test_moderating_reviews_requires_permission(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $noPermissionUser = User::factory()->create(['current_store_id' => $store->id]);
        $product = Product::factory()->for($store)->create();
        $review = Review::factory()->for($store)->for($product)->create(['status' => 'pending']);

        $this->actingAs($noPermissionUser, 'sanctum')
            ->postJson("/api/v1/reviews/{$review->id}/approve")
            ->assertStatus(403);
    }
}
