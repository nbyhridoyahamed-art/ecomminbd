<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
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

    public function test_increasing_stock_creates_a_level_and_a_movement(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'increase',
                'quantity' => 50,
                'reason' => 'Initial stock',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment_increase')
            ->assertJsonPath('data.quantity_before', 0)
            ->assertJsonPath('data.quantity_after', 50);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
        ]);
        $this->assertDatabaseCount('stock_movements', 1);
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

    public function test_adjusting_a_variants_stock_creates_a_level_distinct_from_the_products_own(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        ['product' => $product, 'variant' => $variant] = $this->variantProduct($store);

        // A decoy variant-less row for the same product — adjusting the
        // variant must never touch this one.
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 999]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'increase',
                'quantity' => 50,
                'reason' => 'Initial stock',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_before', 0)
            ->assertJsonPath('data.quantity_after', 50)
            ->assertJsonPath('data.product_variant.id', $variant->id);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'product_variant_id' => null, 'warehouse_id' => $warehouse->id, 'quantity' => 999,
        ]);
    }

    public function test_a_variant_that_does_not_belong_to_the_selected_product_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        ['product' => $product] = $this->variantProduct($store);
        ['variant' => $otherProductsVariant] = $this->variantProduct($store);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'product_variant_id' => $otherProductsVariant->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'increase',
                'quantity' => 10,
            ])
            ->assertUnprocessable()->assertJsonValidationErrors('product_variant_id');
    }

    public function test_decreasing_stock_below_zero_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 5]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'decrease',
                'quantity' => 10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_decreasing_stock_within_available_quantity_succeeds(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockLevel::factory()->for($product)->for($warehouse)->create(['quantity' => 20]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'decrease',
                'quantity' => 8,
                'reason' => 'Damaged units',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'adjustment_decrease')
            ->assertJsonPath('data.quantity_after', 12);

        $this->assertDatabaseHas('stock_levels', ['product_id' => $product->id, 'quantity' => 12]);
    }

    public function test_product_and_warehouse_must_belong_to_the_same_store(): void
    {
        $admin = $this->admin();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($storeA)->create();
        $product = Product::factory()->for($storeB)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'increase',
                'quantity' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_a_user_without_inventory_adjust_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/stock-adjustments', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'increase',
                'quantity' => 10,
            ])
            ->assertForbidden();
    }

    public function test_movements_ledger_can_be_filtered_by_product_and_type(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        StockMovement::factory()->for($store)->for($product)->for($warehouse)->create(['type' => 'adjustment_increase']);
        StockMovement::factory()->for($store)->for($product)->for($warehouse)->create(['type' => 'adjustment_decrease']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/stock-movements?store_id={$store->id}&type=adjustment_increase")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'adjustment_increase');
    }
}
