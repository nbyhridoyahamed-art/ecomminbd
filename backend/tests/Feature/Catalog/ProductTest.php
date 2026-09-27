<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
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

    public function test_a_product_can_be_created_with_decimal_prices_converted_to_minor_units(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'name' => 'Wireless Earbuds',
            'slug' => 'wireless-earbuds',
            'sku' => 'WE-001',
            'price' => '1250.50',
            'sale_price' => '999.99',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.price', 1250.5)
            ->assertJsonPath('data.sale_price', 999.99)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('products', [
            'slug' => 'wireless-earbuds',
            'price_amount' => 125050,
            'sale_price_amount' => 99999,
        ]);
    }

    public function test_setting_status_to_active_sets_published_at(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'name' => 'Active Product',
            'slug' => 'active-product',
            'sku' => 'AP-001',
            'price' => '500',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $product = Product::query()->where('slug', 'active-product')->firstOrFail();
        $this->assertNotNull($product->published_at);
    }

    public function test_sale_price_must_be_lower_than_the_regular_price(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products', [
                'store_id' => $store->id,
                'name' => 'Bad Discount',
                'slug' => 'bad-discount',
                'sku' => 'BD-001',
                'price' => '500',
                'sale_price' => '600',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sale_price');
    }

    public function test_sku_and_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Product::factory()->for($store)->create(['slug' => 'taken', 'sku' => 'TAKEN-1']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products', [
                'store_id' => $store->id,
                'name' => 'Duplicate',
                'slug' => 'taken',
                'sku' => 'NEW-SKU',
                'price' => '100',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products', [
                'store_id' => $store->id,
                'name' => 'Duplicate SKU',
                'slug' => 'new-slug',
                'sku' => 'TAKEN-1',
                'price' => '100',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_products_can_be_filtered_by_category_brand_and_status(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $category = Category::factory()->for($store)->create();
        $brand = Brand::factory()->for($store)->create();

        Product::factory()->for($store)->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 'active']);
        Product::factory()->for($store)->create(['status' => 'draft']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/products?category_id={$category->id}");
        $response->assertOk()->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/products?brand_id={$brand->id}");
        $response->assertOk()->assertJsonCount(1, 'data');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/products?status=draft');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_products_can_be_searched_by_name_or_sku(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Product::factory()->for($store)->create(['name' => 'Cotton T-Shirt', 'sku' => 'CTS-001']);
        Product::factory()->for($store)->create(['name' => 'Leather Wallet', 'sku' => 'LW-001']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/products?search=Cotton')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/products?search=LW-001')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_product_can_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk();

        $this->assertSoftDeleted(Product::class, ['id' => $product->id]);
    }

    public function test_a_user_without_products_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }
}
