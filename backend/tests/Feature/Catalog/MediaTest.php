<?php

namespace Tests\Feature\Catalog;

use App\Models\Media;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
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

    public function test_an_upload_creates_a_media_row_and_a_real_file(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/media', [
            'image' => UploadedFile::fake()->image('banner.jpg'),
            'alt_text' => 'Homepage banner',
            'store_id' => $store->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.alt_text', 'Homepage banner');
        Storage::disk('public')->assertExists($response->json('data.path'));
        $this->assertDatabaseHas('media', ['store_id' => $store->id, 'alt_text' => 'Homepage banner']);
    }

    public function test_media_is_scoped_to_the_requested_store(): void
    {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $admin = $this->admin();

        Media::factory()->for($storeA)->create(['filename' => 'a.jpg']);
        Media::factory()->for($storeB)->create(['filename' => 'b.jpg']);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/media?store_id={$storeA->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.filename', 'a.jpg');
    }

    public function test_listing_media_requires_a_store_id(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/media')->assertUnprocessable();
    }

    public function test_media_can_be_searched_by_filename(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();

        Media::factory()->for($store)->create(['filename' => 'summer-sale-banner.jpg']);
        Media::factory()->for($store)->create(['filename' => 'winter-logo.png']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/media?store_id={$store->id}&search=summer")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'summer-sale-banner.jpg');
    }

    public function test_alt_text_can_be_updated(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();
        $media = Media::factory()->for($store)->create(['alt_text' => 'Old caption']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/media/{$media->id}", ['alt_text' => 'New caption'])
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'New caption');
    }

    public function test_deleting_media_removes_the_row_and_the_file(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();

        $upload = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/media', [
            'image' => UploadedFile::fake()->image('to-delete.jpg'),
            'store_id' => $store->id,
        ])->json('data');

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/media/{$upload['id']}")->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $upload['id']]);
        Storage::disk('public')->assertMissing($upload['path']);
    }

    public function test_media_actions_require_permission(): void
    {
        $store = Store::factory()->create();
        $noPermissionUser = User::factory()->create();

        $this->actingAs($noPermissionUser, 'sanctum')
            ->postJson('/api/v1/media', ['image' => UploadedFile::fake()->image('x.jpg'), 'store_id' => $store->id])
            ->assertForbidden();

        $this->actingAs($noPermissionUser, 'sanctum')
            ->getJson("/api/v1/media?store_id={$store->id}")
            ->assertForbidden();
    }

    public function test_the_legacy_upload_endpoint_also_registers_a_media_row(): void
    {
        $store = Store::factory()->create();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/uploads', [
            'folder' => 'categories',
            'image' => UploadedFile::fake()->image('cat.jpg'),
            'store_id' => $store->id,
        ]);

        $this->assertDatabaseHas('media', ['store_id' => $store->id, 'path' => $response->json('data.path')]);
    }
}
