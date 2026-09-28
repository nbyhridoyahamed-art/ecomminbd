<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
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

    /** @return array{product: Product, colorRed: int, colorBlue: int, sizeS: int, sizeM: int} */
    private function variableProductWithAttributes(Store $store): array
    {
        $product = Product::factory()->for($store)->create(['type' => 'variable', 'sku' => 'TSHIRT']);

        $color = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $colorRed = $color->values()->create(['value' => 'Red', 'slug' => 'red'])->id;
        $colorBlue = $color->values()->create(['value' => 'Blue', 'slug' => 'blue'])->id;

        $size = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Size', 'slug' => 'size']);
        $sizeS = $size->values()->create(['value' => 'S', 'slug' => 's'])->id;
        $sizeM = $size->values()->create(['value' => 'M', 'slug' => 'm'])->id;

        return compact('product', 'colorRed', 'colorBlue', 'sizeS', 'sizeM');
    }

    public function test_generating_variants_creates_the_cartesian_product(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['product' => $product, 'colorRed' => $colorRed, 'colorBlue' => $colorBlue, 'sizeS' => $sizeS, 'sizeM' => $sizeM] =
            $this->variableProductWithAttributes($store);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [$colorRed, $colorBlue, $sizeS, $sizeM],
            ])
            ->assertCreated();

        $response->assertJsonCount(4, 'data');
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'TSHIRT-RED-S']);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'TSHIRT-BLUE-M']);
    }

    public function test_regenerating_with_a_new_value_only_adds_the_new_combinations(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['product' => $product, 'colorRed' => $colorRed, 'sizeS' => $sizeS, 'sizeM' => $sizeM] =
            $this->variableProductWithAttributes($store);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [$colorRed, $sizeS],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        // Adding Size:M alongside the existing Color:Red — should only
        // create the one new combination, not duplicate Red+S.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [$colorRed, $sizeS, $sizeM],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_variants_cannot_be_generated_for_a_simple_product(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'simple']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [$value->id]])
            ->assertStatus(422);
    }

    public function test_a_variant_can_be_updated_with_a_price_override_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['product' => $product, 'colorRed' => $colorRed] = $this->variableProductWithAttributes($store);

        $variantId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [$colorRed]])
            ->assertCreated()
            ->json('data.0.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}/variants/{$variantId}", [
                'sku' => 'TSHIRT-RED-CUSTOM',
                'price' => '650.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.sku', 'TSHIRT-RED-CUSTOM')
            ->assertJsonPath('data.price', 650);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}/variants/{$variantId}")
            ->assertOk();

        $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
    }

    public function test_a_variant_with_stock_records_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        ['product' => $product, 'colorRed' => $colorRed] = $this->variableProductWithAttributes($store);

        $variantId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [$colorRed]])
            ->assertCreated()
            ->json('data.0.id');

        StockLevel::create(['product_id' => $product->id, 'product_variant_id' => $variantId, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}/variants/{$variantId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('product_variants', ['id' => $variantId]);
    }

    public function test_a_variants_stock_summary_totals_quantity_across_warehouses(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();
        ['product' => $product, 'colorRed' => $colorRed] = $this->variableProductWithAttributes($store);

        $variantId = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [$colorRed]])
            ->assertCreated()
            ->json('data.0.id');

        StockLevel::create(['product_id' => $product->id, 'product_variant_id' => $variantId, 'warehouse_id' => $warehouseA->id, 'quantity' => 10, 'quantity_reserved' => 2]);
        StockLevel::create(['product_id' => $product->id, 'product_variant_id' => $variantId, 'warehouse_id' => $warehouseB->id, 'quantity' => 5, 'quantity_reserved' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.stock_summary.total_quantity', 15)
            ->assertJsonPath('data.variants.0.stock_summary.total_reserved', 2)
            ->assertJsonPath('data.variants.0.stock_summary.total_available', 13)
            ->assertJsonCount(2, 'data.variants.0.stock_summary.by_warehouse');
    }

    public function test_variant_skus_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        ['product' => $product, 'colorRed' => $colorRed, 'colorBlue' => $colorBlue] =
            $this->variableProductWithAttributes($store);

        $ids = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", [
                'attribute_value_ids' => [$colorRed, $colorBlue],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}/variants/{$ids[1]['id']}", ['sku' => $ids[0]['sku']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_a_user_without_products_update_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);

        $this->actingAs($viewer, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/variants/generate", ['attribute_value_ids' => [$value->id]])
            ->assertForbidden();
    }
}
