<?php

namespace Tests\Feature\Catalog;

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
        Storage::disk('public')->assertMissing($first['path']);
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
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/uploads', [
                'folder' => 'categories',
                'image' => UploadedFile::fake()->image('logo.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_the_generic_upload_endpoint_stores_and_returns_a_url(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/uploads', [
            'folder' => 'brands',
            'image' => UploadedFile::fake()->image('logo.jpg'),
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['path', 'url']]);
        Storage::disk('public')->assertExists($response->json('data.path'));
    }
}
