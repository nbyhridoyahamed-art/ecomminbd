<?php

namespace Tests\Feature\Foundation;

use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_a_super_admin_can_list_stores(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_a_user_without_the_stores_view_permission_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff'); // has no stores.* permissions

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/stores')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_a_user_with_no_role_cannot_perform_any_protected_action(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/stores')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/warehouses')
            ->assertForbidden();
    }

    public function test_a_viewer_role_can_read_but_not_write_stores(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');
        $organization = Organization::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/stores')
            ->assertOk();

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/v1/stores', [
                'organization_id' => $organization->id,
                'name' => 'New Store',
                'slug' => 'new-store',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_delete_a_store(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $organization = Organization::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/stores', [
            'organization_id' => $organization->id,
            'name' => 'Second Store',
            'slug' => 'second-store',
        ]);

        $response->assertCreated()->assertJsonPath('data.slug', 'second-store');

        $storeId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/stores/{$storeId}")
            ->assertOk();

        $this->assertSoftDeleted(Store::class, ['id' => $storeId]);
    }

    public function test_a_super_admin_can_list_roles(): void
    {
        // Regression test: Spatie's Role model lives outside App\Models, so
        // Laravel's policy auto-discovery never finds RolePolicy on its own
        // — it must be registered explicitly in AppServiceProvider. This
        // test catches it if that registration is ever removed.
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_roles_can_be_assigned_to_a_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $target = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'roles' => ['Inventory Manager'],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'Inventory Manager');

        $this->assertTrue($target->fresh()->hasRole('Inventory Manager'));
    }
}
