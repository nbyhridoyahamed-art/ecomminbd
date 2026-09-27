<?php

namespace Tests\Feature\Delivery;

use App\Models\Courier;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierTest extends TestCase
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

    public function test_a_courier_can_be_created(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/couriers', [
                'store_id' => $store->id,
                'name' => 'Pathao Courier',
                'phone' => '01712345678',
                'tracking_url_template' => 'https://pathao.com/track/{tracking_number}',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pathao Courier')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('couriers', ['name' => 'Pathao Courier']);
    }

    public function test_a_courier_can_be_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $courier = Courier::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/couriers/{$courier->id}", [
                'store_id' => $store->id,
                'name' => 'Updated Courier',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Courier');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/couriers/{$courier->id}")
            ->assertOk();

        $this->assertSoftDeleted('couriers', ['id' => $courier->id]);
    }

    public function test_a_user_without_couriers_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/couriers?store_id={$store->id}")
            ->assertForbidden();
    }
}
