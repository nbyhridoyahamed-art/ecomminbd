<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
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

    public function test_brands_can_be_listed_with_pagination_meta(): void
    {
        $store = Store::factory()->create();
        Brand::factory()->for($store)->count(3)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_a_user_without_brands_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/brands')
            ->assertForbidden();
    }

    public function test_a_brand_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/brands', [
            'store_id' => $store->id,
            'name' => 'Acme',
            'slug' => 'acme',
        ]);
        $create->assertCreated();
        $brandId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/brands/{$brandId}", [
                'store_id' => $store->id,
                'name' => 'Acme Corp',
                'slug' => 'acme',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Corp');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/brands/{$brandId}")
            ->assertOk();

        $this->assertSoftDeleted(Brand::class, ['id' => $brandId]);
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Brand::factory()->for($store)->create(['slug' => 'acme']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/brands', [
                'store_id' => $store->id,
                'name' => 'Another Acme',
                'slug' => 'acme',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
