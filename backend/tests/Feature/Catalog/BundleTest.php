<?php

namespace Tests\Feature\Catalog;

use App\Models\BundleItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleTest extends TestCase
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

    /** @return array{bundle: Product, widget: Product, gadget: Product} */
    private function bundleWithComponents(Store $store, int $widgetQtyNeeded = 2, int $gadgetQtyNeeded = 1): array
    {
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'name' => 'Combo Pack']);
        $widget = Product::factory()->for($store)->create(['name' => 'Widget']);
        $gadget = Product::factory()->for($store)->create(['name' => 'Gadget']);

        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $widget->id, 'quantity' => $widgetQtyNeeded]);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $gadget->id, 'quantity' => $gadgetQtyNeeded]);

        return compact('bundle', 'widget', 'gadget');
    }

    /** @return array{product: Product, variant: ProductVariant} */
    private function variantProduct(Store $store): array
    {
        $product = Product::factory()->for($store)->create(['type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color-'.$product->id]);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red-'.$product->id]);

        $variant = ProductVariant::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-RED',
            'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        return compact('product', 'variant');
    }

    public function test_creating_a_bundle_forces_track_stock_off_regardless_of_input(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'name' => 'Combo Pack',
            'slug' => 'combo-pack',
            'sku' => 'COMBO-1',
            'price' => '999.00',
            'type' => 'bundle',
            'track_stock' => true,
            'low_stock_threshold' => 5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.low_stock_threshold', null);

        $this->assertDatabaseHas('products', ['sku' => 'COMBO-1', 'track_stock' => false, 'low_stock_threshold' => null]);
    }

    public function test_updating_a_product_to_bundle_type_forces_track_stock_off(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'simple', 'track_stock' => true, 'low_stock_threshold' => 5]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/products/{$product->id}", [
            'store_id' => $store->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => '10.00',
            'type' => 'bundle',
            'track_stock' => true,
            'low_stock_threshold' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.low_stock_threshold', null);
    }

    public function test_a_component_can_be_added_to_a_bundle(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        $widget = Product::factory()->for($store)->create(['name' => 'Widget']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $widget->id)
            ->assertJsonPath('data.product_name', 'Widget')
            ->assertJsonPath('data.quantity', 2);

        $this->assertDatabaseHas('bundle_items', [
            'bundle_product_id' => $bundle->id, 'component_product_id' => $widget->id, 'quantity' => 2,
        ]);
    }

    public function test_a_component_can_target_a_specific_variant(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        ['product' => $product, 'variant' => $variant] = $this->variantProduct($store);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_variant_id', $variant->id)
            ->assertJsonPath('data.product_variant_sku', $variant->sku);

        $this->assertDatabaseHas('bundle_items', [
            'bundle_product_id' => $bundle->id, 'component_product_id' => $product->id, 'component_variant_id' => $variant->id,
        ]);
    }

    public function test_a_variant_that_does_not_belong_to_the_selected_component_product_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        ['product' => $product] = $this->variantProduct($store);
        ['variant' => $otherProductsVariant] = $this->variantProduct($store);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $product->id,
            'product_variant_id' => $otherProductsVariant->id,
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
    }

    public function test_components_cannot_be_added_to_a_non_bundle_product(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'simple']);
        $widget = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$product->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_a_bundle_cannot_contain_itself_as_a_component(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $bundle->id,
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function test_a_bundle_component_cannot_itself_be_a_bundle(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundleA = Product::factory()->for($store)->create(['type' => 'bundle']);
        $bundleB = Product::factory()->for($store)->create(['type' => 'bundle']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundleA->id}/components", [
            'product_id' => $bundleB->id,
            'quantity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function test_a_duplicate_component_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        $widget = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function test_a_component_can_be_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        $widget = Product::factory()->for($store)->create();

        $componentId = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 1,
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/products/{$bundle->id}/components/{$componentId}", [
            'quantity' => 5,
        ])->assertOk()->assertJsonPath('data.quantity', 5);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/products/{$bundle->id}/components/{$componentId}")->assertOk();

        $this->assertDatabaseMissing('bundle_items', ['id' => $componentId]);
    }

    public function test_updating_or_deleting_a_component_that_does_not_belong_to_the_bundle_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $bundle1 = Product::factory()->for($store)->create(['type' => 'bundle']);
        $bundle2 = Product::factory()->for($store)->create(['type' => 'bundle']);
        $widget = Product::factory()->for($store)->create();

        $componentId = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/products/{$bundle1->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 1,
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/products/{$bundle2->id}/components/{$componentId}", ['quantity' => 9])
            ->assertStatus(404);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$bundle2->id}/components/{$componentId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('bundle_items', ['id' => $componentId, 'quantity' => 1]);
    }

    public function test_a_user_without_products_update_is_forbidden_from_managing_components(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $bundle = Product::factory()->for($store)->create(['type' => 'bundle']);
        $widget = Product::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/products/{$bundle->id}/components", [
            'product_id' => $widget->id,
            'quantity' => 1,
        ])->assertForbidden();
    }

    public function test_the_product_resource_exposes_components_and_bundle_availability(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        ['bundle' => $bundle, 'widget' => $widget, 'gadget' => $gadget] = $this->bundleWithComponents($store);
        StockLevel::create(['product_id' => $widget->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'quantity_reserved' => 0]);
        StockLevel::create(['product_id' => $gadget->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/products/{$bundle->id}")->assertOk();

        $response->assertJsonCount(2, 'data.components');
        $this->assertNotNull($response->json('data.bundle_availability'));
        $this->assertArrayHasKey('total_available', $response->json('data.bundle_availability'));
        $this->assertArrayHasKey('by_warehouse', $response->json('data.bundle_availability'));
    }

    public function test_bundle_availability_is_absent_for_a_non_bundle_product(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'simple']);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertArrayNotHasKey('bundle_availability', $response->json('data'));
    }

    public function test_bundle_availability_is_the_floor_division_minimum_across_components_per_warehouse(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouseA = Warehouse::factory()->for($store)->create();
        $warehouseB = Warehouse::factory()->for($store)->create();
        // Bundle needs 2x widget + 1x gadget per unit.
        ['bundle' => $bundle, 'widget' => $widget, 'gadget' => $gadget] = $this->bundleWithComponents($store);

        // Warehouse A: widget available 8 (10 - 2 reserved) -> floor(8/2)=4;
        // gadget available 5 -> floor(5/1)=5. min=4.
        StockLevel::create(['product_id' => $widget->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 10, 'quantity_reserved' => 2]);
        StockLevel::create(['product_id' => $gadget->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 5, 'quantity_reserved' => 0]);

        // Warehouse B: widget available 6 -> floor(6/2)=3; gadget has no row
        // at all here -> treated as 0 available -> floor(0/1)=0. min=0.
        StockLevel::create(['product_id' => $widget->id, 'warehouse_id' => $warehouseB->id, 'quantity' => 6, 'quantity_reserved' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/products/{$bundle->id}")->assertOk();

        $response->assertJsonPath('data.bundle_availability.total_available', 4)
            ->assertJsonCount(2, 'data.bundle_availability.by_warehouse');
    }
}
