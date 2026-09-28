<?php

namespace Tests\Feature\Seo;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSeoTest extends TestCase
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

    public function test_a_store_with_no_seo_override_returns_null(): void
    {
        $store = Store::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/store-seo?store_id={$store->id}")
            ->assertOk()
            ->assertJsonPath('data.store_id', $store->id)
            ->assertJsonPath('data.seo', null);
    }

    public function test_site_wide_seo_can_be_set_and_read_back(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/store-seo', [
                'store_id' => $store->id,
                'seo' => ['title' => 'My Store | Best Deals in Town', 'description' => 'Site-wide description'],
            ])
            ->assertOk()
            ->assertJsonPath('data.seo.title', 'My Store | Best Deals in Town');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/store-seo?store_id={$store->id}")
            ->assertOk()
            ->assertJsonPath('data.seo.title', 'My Store | Best Deals in Town')
            ->assertJsonPath('data.seo.description', 'Site-wide description');

        $this->assertDatabaseCount('seo_metadata', 1);
        $this->assertDatabaseHas('seo_metadata', [
            'entity_type' => Store::class,
            'entity_id' => $store->id,
        ]);
    }

    public function test_updating_site_wide_seo_again_does_not_create_a_second_row(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/store-seo', [
            'store_id' => $store->id,
            'seo' => ['title' => 'First title'],
        ])->assertOk();

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/store-seo', [
            'store_id' => $store->id,
            'seo' => ['title' => 'Second title'],
        ])->assertOk()->assertJsonPath('data.seo.title', 'Second title');

        $this->assertDatabaseCount('seo_metadata', 1);
    }

    public function test_a_user_without_seo_manage_is_forbidden(): void
    {
        $store = Store::factory()->create();
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/store-seo?store_id={$store->id}")
            ->assertForbidden();
    }
}
