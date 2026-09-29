<?php

namespace Tests\Feature\Storefront;

use App\Models\BundleItem;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function guestPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Karim Rahman',
            'customer_phone' => '01712345678',
            'customer_email' => 'karim@example.com',
            'shipping_recipient_name' => 'Karim Rahman',
            'shipping_phone' => '01712345678',
            'shipping_address_line' => 'House 1, Road 2, Dhaka',
        ], $overrides);
    }

    public function test_a_guest_can_place_a_cod_order_without_authentication(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active', 'price_amount' => 19900]);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $response = $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.items.0.unit_price', 199)
            ->assertJsonPath('data.subtotal_amount', 398)
            ->assertJsonPath('data.total_amount', 398)
            ->assertJsonStructure(['data' => ['uuid']]);

        $this->assertDatabaseHas('orders', ['id' => Order::firstOrFail()->id, 'source' => 'storefront', 'warehouse_id' => $warehouse->id]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'quantity_reserved' => 2,
        ]);
        $this->assertDatabaseHas('customers', ['store_id' => $store->id, 'phone' => '01712345678', 'name' => 'Karim Rahman']);
    }

    public function test_the_public_response_never_exposes_the_sequential_order_id_or_internal_fields(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $response = $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated();

        $response->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.warehouse')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.customer');
    }

    public function test_repeat_checkout_with_the_same_phone_reuses_the_existing_customer_and_never_renames_it(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated();

        $this->assertSame(1, Customer::count());

        // Same phone, different name on the second order — must not rewrite
        // the customer record created by the first one.
        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'customer_name' => 'Someone Else',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['phone' => '01712345678', 'name' => 'Karim Rahman']);
        $this->assertSame(2, Order::count());
    }

    public function test_a_client_submitted_unit_price_is_ignored_and_the_real_catalog_price_is_charged(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active', 'price_amount' => 50000]);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $response = $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '1.00']],
        ]))->assertCreated();

        $response->assertJsonPath('data.items.0.unit_price', 500);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'unit_price_amount' => 50000]);
    }

    public function test_checkout_charges_the_sale_price_when_one_is_set(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create([
            'status' => 'active', 'price_amount' => 50000, 'sale_price_amount' => 39900,
        ]);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated()->assertJsonPath('data.items.0.unit_price', 399);
    }

    public function test_an_inactive_or_missing_product_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $draft = Product::factory()->for($store)->create(['status' => 'draft']);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $draft->id, 'quantity' => 1]],
        ]))->assertStatus(422);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => 999999, 'quantity' => 1]],
        ]))->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_duplicate_products_in_the_cart_are_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_a_variant_that_does_not_belong_to_the_product_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        $otherProduct = Product::factory()->for($store)->create(['status' => 'active']);
        $otherVariant = ProductVariant::create([
            'store_id' => $store->id, 'product_id' => $otherProduct->id, 'sku' => 'OTHER-VAR', 'status' => 'active',
        ]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'product_variant_id' => $otherVariant->id, 'quantity' => 1]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.product_variant_id');
    }

    public function test_an_absurd_quantity_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 999]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_insufficient_stock_at_the_only_warehouse_is_rejected_and_nothing_is_reserved(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 1, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]))->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1, 'quantity_reserved' => 0,
        ]);
    }

    public function test_the_warehouse_that_can_fully_cover_the_cart_is_selected_automatically(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $shortWarehouse = Warehouse::factory()->for($store)->create();
        $fullWarehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($shortWarehouse)->create(['quantity' => 1, 'quantity_reserved' => 0]);
        StockLevel::factory()->for($product)->for($fullWarehouse)->create(['quantity' => 20, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]))->assertCreated();

        $order = Order::firstOrFail();
        $this->assertSame($fullWarehouse->id, $order->warehouse_id);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $fullWarehouse->id, 'quantity' => 20, 'quantity_reserved' => 5,
        ]);
    }

    public function test_checking_out_a_bundle_reserves_each_components_stock(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'status' => 'active', 'price_amount' => 30000]);
        $widget = Product::factory()->for($store)->create(['status' => 'active']);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $widget->id, 'quantity' => 2]);
        StockLevel::factory()->for($widget)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $bundle->id, 'quantity' => 3]],
        ]))->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $widget->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'quantity_reserved' => 6,
        ]);
    }

    public function test_an_order_can_be_looked_up_by_uuid_but_not_by_its_sequential_id(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated();

        $order = Order::firstOrFail();

        $this->getJson("/api/v1/storefront/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number);

        $this->getJson("/api/v1/storefront/orders/{$order->id}")->assertNotFound();
    }

    public function test_a_storefront_order_is_visible_to_admins_with_source_storefront(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]))->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/orders?store_id='.$store->id)->assertOk();

        $response->assertJsonPath('data.0.source', 'storefront');
    }

    public function test_checkout_applies_a_valid_coupon_code(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active', 'price_amount' => 100000]);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);
        $coupon = Coupon::factory()->for($store)->create(['code' => 'WELCOME10', 'percentage_value' => 10]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'welcome10',
        ]))->assertCreated()
            ->assertJsonPath('data.subtotal_amount', 1000)
            ->assertJsonPath('data.discount_amount', 100)
            ->assertJsonPath('data.total_amount', 900)
            ->assertJsonPath('data.coupon_code', 'WELCOME10');

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_checkout_rejects_an_invalid_coupon_code_and_creates_no_order(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $this->postJson('/api/v1/storefront/checkout', $this->guestPayload([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'coupon_code' => 'NOPE',
        ]))->assertStatus(422);

        $this->assertSame(0, Order::count());
    }
}
