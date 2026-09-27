<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_a_customer_can_be_created(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/customers', [
                'store_id' => $store->id,
                'name' => 'Karim Rahman',
                'phone' => '01712345678',
                'email' => 'karim@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Karim Rahman')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('customers', ['phone' => '01712345678']);
    }

    public function test_customers_can_be_searched_by_name_phone_or_email(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Customer::factory()->for($store)->create(['name' => 'Karim Rahman', 'phone' => '01711111111']);
        Customer::factory()->for($store)->create(['name' => 'Fatima Begum', 'phone' => '01722222222']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/customers?store_id={$store->id}&search=Karim")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Karim Rahman');
    }

    public function test_a_customer_can_be_updated(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/customers/{$customer->id}", [
                'store_id' => $store->id,
                'name' => 'Updated Name',
                'phone' => $customer->phone,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_a_customer_can_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_a_user_without_customers_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/customers?store_id={$store->id}")
            ->assertForbidden();
    }
}
