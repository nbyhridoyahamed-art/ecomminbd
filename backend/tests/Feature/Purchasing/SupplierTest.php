<?php

namespace Tests\Feature\Purchasing;

use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    public function test_a_user_with_suppliers_view_can_list_suppliers(): void
    {
        $store = Store::factory()->create();
        Supplier::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/suppliers?store_id={$store->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_suppliers_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/suppliers')
            ->assertForbidden();
    }

    public function test_a_supplier_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/suppliers', [
            'store_id' => $store->id,
            'name' => 'Acme Textiles',
            'email' => 'sales@acme.test',
        ]);
        $create->assertCreated()->assertJsonPath('data.name', 'Acme Textiles')->assertJsonPath('data.status', 'active');
        $supplierId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/suppliers/{$supplierId}", [
                'store_id' => $store->id,
                'name' => 'Acme Textiles Ltd',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Textiles Ltd');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/suppliers/{$supplierId}")
            ->assertOk();

        $this->assertSoftDeleted(Supplier::class, ['id' => $supplierId]);
    }
}
