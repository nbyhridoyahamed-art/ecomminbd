<?php

namespace Tests\Feature\Catalog;

use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_the_first_uploaded_image_is_automatically_marked_primary(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('front.jpg'), 'alt_text' => 'Front view'],
        );

        $response->assertCreated()->assertJsonPath('data.is_primary', true);
        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    public function test_deleting_the_primary_image_promotes_the_next_one(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $first = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('a.jpg')],
        )->json('data');

        $second = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('b.jpg')],
        )->json('data');

        $this->assertTrue($first['is_primary']);
        $this->assertFalse($second['is_primary']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}/images/{$first['id']}")
            ->assertOk();

        $this->assertDatabaseHas('product_images', ['id' => $second['id'], 'is_primary' => true]);
        $this->assertDatabaseMissing('product_images', ['id' => $first['id']]);
        // The underlying file is deliberately NOT removed from disk — the
        // Media Library lets the same upload be picked for more than one
        // entity, so unlinking a product's own reference to it must never
        // delete a file another reference still points at. Only the
        // library's own explicit delete (MediaController@destroy) does that.
        Storage::disk('public')->assertExists($first['path']);
    }

    public function test_marking_a_different_image_as_primary_unsets_the_others(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $first = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('a.jpg')],
        )->json('data');

        $second = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('b.jpg')],
        )->json('data');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/images/{$second['id']}/primary")
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('product_images', ['id' => $first['id'], 'is_primary' => false]);
        $this->assertDatabaseHas('product_images', ['id' => $second['id'], 'is_primary' => true]);
    }

    public function test_uploading_a_product_image_also_registers_it_in_the_media_library(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images",
            ['image' => UploadedFile::fake()->image('front.jpg')],
        );

        $this->assertDatabaseHas('media', ['store_id' => $store->id, 'path' => $response->json('data.path')]);
    }

    public function test_an_existing_media_library_item_can_be_attached_without_a_new_upload(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();
        $media = Media::factory()->for($store)->create(['alt_text' => 'Reused banner']);

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/v1/products/{$product->id}/images/attach",
            ['media_id' => $media->id],
        );

        $response->assertCreated()
            ->assertJsonPath('data.path', $media->path)
            ->assertJsonPath('data.alt_text', 'Reused banner')
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'path' => $media->path]);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_attaching_media_from_a_different_store_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $product = Product::factory()->for($store)->create();
        $media = Media::factory()->for($otherStore)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/images/attach", ['media_id' => $media->id])
            ->assertNotFound();
    }

    public function test_non_image_files_are_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson(
                "/api/v1/products/{$product->id}/images",
                ['image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_the_generic_upload_endpoint_requires_category_or_brand_permission(): void
    {
        $store = Store::factory()->create();
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'folder' => 'categories',
                'store_id' => $store->id,
                'image' => UploadedFile::fake()->image('logo.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_the_generic_upload_endpoint_stores_and_returns_a_url(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/uploads', [
            'folder' => 'brands',
            'store_id' => $store->id,
            'image' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['path', 'url']]);
        Storage::disk('public')->assertExists($response->json('data.path'));
    }
}
