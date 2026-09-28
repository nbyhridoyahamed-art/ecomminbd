<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportExportTest extends TestCase
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

    private function csvFile(array $header, array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('products.csv', $contents);
    }

    public function test_importing_a_new_row_creates_a_simple_product(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price'],
            [['NEW-SKU-1', 'Imported Widget', '199.50']],
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseHas('products', [
            'store_id' => $store->id, 'sku' => 'NEW-SKU-1', 'name' => 'Imported Widget',
            'price_amount' => 19950, 'type' => 'simple', 'status' => 'draft',
        ]);
    }

    public function test_importing_an_existing_sku_updates_it_without_touching_type_or_variants(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create([
            'sku' => 'EXISTING-1', 'name' => 'Old Name', 'type' => 'variable', 'price_amount' => 10000,
        ]);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);
        $variant = ProductVariant::create([
            'store_id' => $store->id, 'product_id' => $product->id, 'sku' => 'EXISTING-1-RED', 'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price'],
            [['EXISTING-1', 'Updated Name', '250.00']],
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id, 'name' => 'Updated Name', 'price_amount' => 25000, 'type' => 'variable',
        ]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'sku' => 'EXISTING-1-RED']);
    }

    public function test_importing_auto_creates_a_missing_category_and_brand_by_name(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price', 'Category', 'Brand'],
            [['NEW-SKU-2', 'Branded Widget', '99.00', 'New Category', 'New Brand']],
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $category = Category::query()->where('store_id', $store->id)->where('name', 'New Category')->first();
        $brand = Brand::query()->where('store_id', $store->id)->where('name', 'New Brand')->first();
        $this->assertNotNull($category);
        $this->assertNotNull($brand);
        $this->assertDatabaseHas('products', [
            'sku' => 'NEW-SKU-2', 'category_id' => $category->id, 'brand_id' => $brand->id,
        ]);
    }

    public function test_reusing_an_existing_category_by_name_does_not_create_a_duplicate(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $category = Category::factory()->for($store)->create(['name' => 'Shoes']);

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price', 'Category'],
            [['NEW-SKU-3', 'A Shoe', '50.00', 'shoes']],
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk();

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('products', ['sku' => 'NEW-SKU-3', 'category_id' => $category->id]);
    }

    public function test_an_invalid_row_is_skipped_and_reported_while_valid_rows_still_import(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price'],
            [
                ['GOOD-SKU', 'Good Product', '10.00'],
                ['BAD-SKU', '', '10.00'],
            ],
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertCount(1, $response->json('data.errors'));
        $this->assertSame(3, $response->json('data.errors.0.row'));
        $this->assertDatabaseHas('products', ['sku' => 'GOOD-SKU']);
        $this->assertDatabaseMissing('products', ['sku' => 'BAD-SKU']);
    }

    public function test_a_blank_status_cell_leaves_an_existing_products_status_untouched(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Product::factory()->for($store)->create(['sku' => 'ACTIVE-1', 'status' => 'active']);

        $file = $this->csvFile(
            ['SKU', 'Name', 'Price', 'Status'],
            [['ACTIVE-1', 'Renamed', '10.00', '']],
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertOk();

        $this->assertDatabaseHas('products', ['sku' => 'ACTIVE-1', 'status' => 'active', 'name' => 'Renamed']);
    }

    public function test_a_user_without_products_create_is_forbidden_from_importing(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $store = Store::factory()->create();

        $file = $this->csvFile(['SKU', 'Name', 'Price'], [['X', 'Y', '1.00']]);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/products/import', ['store_id' => $store->id, 'file' => $file])
            ->assertForbidden();
    }

    public function test_export_streams_only_products_matching_the_filters(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Product::factory()->for($store)->create(['sku' => 'MATCH-1', 'name' => 'Match Me', 'status' => 'active']);
        Product::factory()->for($store)->create(['sku' => 'OTHER-1', 'name' => 'Other Product', 'status' => 'draft']);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/products/export?store_id={$store->id}&status=active");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('MATCH-1', $content);
        $this->assertStringNotContainsString('OTHER-1', $content);
    }

    public function test_a_user_without_products_view_is_forbidden_from_exporting(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($staff, 'sanctum')
            ->get("/api/v1/products/export?store_id={$store->id}")
            ->assertForbidden();
    }
}
