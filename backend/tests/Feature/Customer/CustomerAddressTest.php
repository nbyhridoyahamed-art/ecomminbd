<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressTest extends TestCase
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

    public function test_the_first_address_added_becomes_the_default_automatically(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/customers/{$customer->id}/addresses", [
                'recipient_name' => 'Karim Rahman',
                'phone' => '01712345678',
                'address_line' => 'House 1, Road 2, Dhaka',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_marking_a_new_address_default_unsets_the_previous_default(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();
        $first = $customer->addresses()->create([
            'recipient_name' => 'First', 'phone' => '01711111111', 'address_line' => 'Address A', 'is_default' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/customers/{$customer->id}/addresses", [
                'recipient_name' => 'Second',
                'phone' => '01722222222',
                'address_line' => 'Address B',
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->refresh()->is_default);
    }

    public function test_deleting_the_default_address_promotes_another_one(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customer = Customer::factory()->for($store)->create();
        $default = $customer->addresses()->create([
            'recipient_name' => 'First', 'phone' => '01711111111', 'address_line' => 'Address A', 'is_default' => true,
        ]);
        $other = $customer->addresses()->create([
            'recipient_name' => 'Second', 'phone' => '01722222222', 'address_line' => 'Address B', 'is_default' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/customers/{$customer->id}/addresses/{$default->id}")
            ->assertOk();

        $this->assertTrue($other->refresh()->is_default);
    }

    public function test_an_address_belonging_to_a_different_customer_cannot_be_modified(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $customerA = Customer::factory()->for($store)->create();
        $customerB = Customer::factory()->for($store)->create();
        $address = $customerA->addresses()->create([
            'recipient_name' => 'First', 'phone' => '01711111111', 'address_line' => 'Address A', 'is_default' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/customers/{$customerB->id}/addresses/{$address->id}", [
                'recipient_name' => 'Hacked',
                'phone' => '01700000000',
                'address_line' => 'Nope',
            ])
            ->assertStatus(404);
    }
}
