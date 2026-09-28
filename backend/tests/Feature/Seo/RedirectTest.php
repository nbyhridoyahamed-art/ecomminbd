<?php

namespace Tests\Feature\Seo;

use App\Models\Redirect;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
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

    public function test_a_user_with_seo_manage_can_list_redirects(): void
    {
        $store = Store::factory()->create();
        Redirect::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/redirects')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_seo_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/redirects')
            ->assertForbidden();
    }

    public function test_the_seo_manager_role_can_manage_redirects(): void
    {
        $store = Store::factory()->create();
        $seoManager = User::factory()->create();
        $seoManager->assignRole('SEO Manager');

        $this->actingAs($seoManager, 'sanctum')->postJson('/api/v1/redirects', [
            'store_id' => $store->id,
            'from_path' => '/old-page',
            'to_path' => '/new-page',
        ])->assertCreated();
    }

    public function test_a_redirect_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/redirects', [
            'store_id' => $store->id,
            'from_path' => '/old-page',
            'to_path' => '/new-page',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.from_path', '/old-page')
            ->assertJsonPath('data.status_code', 301)
            ->assertJsonPath('data.hits_count', 0);
        $redirectId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/redirects/{$redirectId}", [
                'store_id' => $store->id,
                'from_path' => '/old-page',
                'to_path' => '/newer-page',
                'status_code' => 302,
            ])
            ->assertOk()
            ->assertJsonPath('data.to_path', '/newer-page')
            ->assertJsonPath('data.status_code', 302);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/redirects/{$redirectId}")
            ->assertOk();

        $this->assertDatabaseMissing('redirects', ['id' => $redirectId]);
    }

    public function test_from_path_must_start_with_a_slash(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/redirects', [
                'store_id' => $store->id,
                'from_path' => 'old-page',
                'to_path' => '/new-page',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_path');
    }

    public function test_from_path_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Redirect::factory()->for($store)->create(['from_path' => '/old-page']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/redirects', [
                'store_id' => $store->id,
                'from_path' => '/old-page',
                'to_path' => '/somewhere-else',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from_path');
    }

    public function test_status_code_must_be_a_valid_redirect_code(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/redirects', [
                'store_id' => $store->id,
                'from_path' => '/old-page',
                'to_path' => '/new-page',
                'status_code' => 200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status_code');
    }
}
