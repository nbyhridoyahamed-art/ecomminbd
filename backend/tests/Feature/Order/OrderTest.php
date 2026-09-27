<?php

namespace Tests\Feature\Order;

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
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

class OrderTest extends TestCase
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

    public function test_creating_an_order_reserves_stock_without_touching_on_hand_quantity(): void
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
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => '199.00'],
            ],
            ...$this->manualShipping(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal_amount', 1990)
            ->assertJsonPath('data.total_amount', 1990);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50, 'quantity_reserved' => 10,
        ]);
        $this->assertDatabaseHas('order_status_history', ['to_status' => 'pending', 'from_status' => null]);
    }

    public function test_creating_an_order_with_insufficient_available_stock_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        // 10 on hand, 8 already reserved -> only 2 available.
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 8]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '10.00'],
            ],
            ...$this->manualShipping(),
        ])->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'quantity_reserved' => 8,
        ]);
    }

    public function test_a_saved_customer_address_is_snapshotted_onto_the_order(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $address = $customer->addresses()->create([
            'recipient_name' => 'Saved Recipient', 'phone' => '01799999999', 'address_line' => 'Saved Address', 'is_default' => true,
        ]);
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'customer_address_id' => $address->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00']],
        ])
            ->assertCreated()
            ->assertJsonPath('data.shipping.recipient_name', 'Saved Recipient')
            ->assertJsonPath('data.shipping.address_line', 'Saved Address');
    }

    public function test_updating_a_pending_order_releases_the_old_reservation_and_reserves_the_new_items(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $productA = Product::factory()->for($store)->create();
        $productB = Product::factory()->for($store)->create();
        StockLevel::factory()->for($productA)->for($warehouse)->create(['quantity' => 50]);
        StockLevel::factory()->for($productB)->for($warehouse)->create(['quantity' => 50]);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $productA->id, 'quantity' => 10, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])->assertCreated();

        $orderId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/orders/{$orderId}", [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $productB->id, 'quantity' => 20, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $productB->id);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $productA->id, 'quantity_reserved' => 0]);
        $this->assertDatabaseHas('stock_levels', ['product_id' => $productB->id, 'quantity_reserved' => 20]);
    }

    public function test_shipping_converts_the_reservation_into_a_real_sale_movement_and_decrements_on_hand_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 15, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])->assertCreated();

        $orderId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/process")
            ->assertOk()->assertJsonPath('data.status', 'processing');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/ship")
            ->assertOk()->assertJsonPath('data.status', 'shipped');

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 35, 'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'sale', 'quantity' => 15,
            'quantity_before' => 50, 'quantity_after' => 35, 'reference_type' => Order::class, 'reference_id' => $orderId,
        ]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/deliver")
            ->assertOk()->assertJsonPath('data.status', 'delivered');
    }

    public function test_cancelling_releases_the_reservation_without_touching_on_hand_stock(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 15, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])->assertCreated();

        $orderId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50, 'quantity_reserved' => 0,
        ]);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_a_shipped_order_can_no_longer_be_edited_or_cancelled(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])->assertCreated();

        $orderId = $create->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/ship")->assertOk();

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/orders/{$orderId}", [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/orders/{$orderId}/cancel")->assertStatus(422);
    }

    public function test_duplicate_products_in_items_are_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00'],
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '10.00'],
            ],
            ...$this->manualShipping(),
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_the_shipping_division_district_and_upazila_names_are_returned(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 50]);

        $division = BdDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => '30']);
        $district = BdDistrict::create(['bd_division_id' => $division->id, 'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => '26']);
        $upazila = BdUpazila::create(['bd_district_id' => $district->id, 'name_en' => 'Savar', 'name_bn' => 'সাভার', 'code' => '75']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00']],
            ...$this->manualShipping(),
            'shipping_bd_division_id' => $division->id,
            'shipping_bd_district_id' => $district->id,
            'shipping_bd_upazila_id' => $upazila->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.shipping.division', 'Dhaka')
            ->assertJsonPath('data.shipping.district', 'Dhaka')
            ->assertJsonPath('data.shipping.upazila', 'Savar');
    }

    public function test_a_user_without_orders_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/orders?store_id={$store->id}")
            ->assertForbidden();
    }
}
