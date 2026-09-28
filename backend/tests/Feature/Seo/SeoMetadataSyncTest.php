<?php

namespace Tests\Feature\Seo;

use App\Models\Page;
use App\Models\Product;
use App\Models\SeoMetadata;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every SEO-bearing entity accepts/returns its SEO overrides as a nested
 * `seo` object on its own existing endpoint (via the shared SyncsSeoMetadata
 * trait) rather than a separate seo-metadata resource. Exercised here
 * against Product and Page — two different controllers — to prove the
 * behavior comes from the shared trait rather than one-off per-entity code.
 */
class SeoMetadataSyncTest extends TestCase
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

    public function test_creating_a_product_with_seo_creates_a_seo_metadata_row(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'name' => 'Demo Product',
            'slug' => 'demo-product',
            'sku' => 'DEMO-1',
            'price' => '19.99',
            'seo' => ['title' => 'Demo Product | Best Price', 'focus_keyword' => 'demo product'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.seo.title', 'Demo Product | Best Price')
            ->assertJsonPath('data.seo.focus_keyword', 'demo product');

        $productId = $response->json('data.id');
        $this->assertDatabaseHas('seo_metadata', [
            'entity_type' => Product::class,
            'entity_id' => $productId,
            'title' => 'Demo Product | Best Price',
        ]);
    }

    public function test_a_product_created_without_seo_returns_a_null_seo_object(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'name' => 'Plain Product',
            'slug' => 'plain-product',
            'sku' => 'PLAIN-1',
            'price' => '9.99',
        ])->assertCreated()->assertJsonPath('data.seo', null);

        $this->assertDatabaseCount('seo_metadata', 0);
    }

    public function test_updating_a_products_seo_updates_the_same_row_rather_than_creating_another(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        SeoMetadata::factory()->create([
            'store_id' => $product->store_id,
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'title' => 'Old title',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'store_id' => $product->store_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => '19.99',
                'seo' => ['title' => 'New title'],
            ])
            ->assertOk()
            ->assertJsonPath('data.seo.title', 'New title');

        $this->assertDatabaseCount('seo_metadata', 1);
        $this->assertDatabaseHas('seo_metadata', ['entity_id' => $product->id, 'title' => 'New title']);
    }

    public function test_updating_a_product_without_sending_seo_leaves_its_existing_seo_untouched(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create();
        SeoMetadata::factory()->create([
            'store_id' => $product->store_id,
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'title' => 'Untouched title',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/products/{$product->id}", [
                'store_id' => $product->store_id,
                'name' => 'Renamed',
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => '19.99',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.seo.title', 'Untouched title');
    }

    public function test_a_page_can_also_receive_nested_seo_via_the_shared_sync_behavior(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/pages', [
            'store_id' => $store->id,
            'title' => 'About Us',
            'slug' => 'about-us',
            'seo' => ['description' => 'Learn more about our store.'],
        ]);

        $response->assertCreated()->assertJsonPath('data.seo.description', 'Learn more about our store.');

        $this->assertDatabaseHas('seo_metadata', [
            'entity_type' => Page::class,
            'entity_id' => $response->json('data.id'),
        ]);
    }
}
