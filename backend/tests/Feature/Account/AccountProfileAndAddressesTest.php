<?php

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileAndAddressesTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndGetToken(): string
    {
        Store::factory()->create(['status' => 'active']);

        return $this->postJson('/api/v1/account/auth/register', [
            'name' => 'Karim Rahman',
            'phone' => '01712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->json('data.token');
    }

    public function test_a_customer_can_update_their_own_profile(): void
    {
        $token = $this->registerAndGetToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/account/profile', ['name' => 'Karim Updated', 'email' => 'updated@example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Karim Updated')
            ->assertJsonPath('data.email', 'updated@example.com');

        $this->assertDatabaseHas('customers', ['name' => 'Karim Updated', 'email' => 'updated@example.com']);
    }

    public function test_a_customer_can_add_list_update_and_delete_their_own_addresses(): void
    {
        $token = $this->registerAndGetToken();
        $auth = $this->withHeader('Authorization', "Bearer {$token}");

        $create = $auth->postJson('/api/v1/account/addresses', [
            'recipient_name' => 'Karim Rahman',
            'phone' => '01712345678',
            'address_line' => 'House 1, Road 2, Dhaka',
        ]);
        $create->assertCreated()->assertJsonPath('data.is_default', true);
        $addressId = $create->json('data.id');

        $auth->getJson('/api/v1/account/addresses')->assertOk()->assertJsonCount(1, 'data');

        $auth->putJson("/api/v1/account/addresses/{$addressId}", [
            'recipient_name' => 'Karim R.',
            'phone' => '01712345678',
            'address_line' => 'House 2, Road 3, Dhaka',
        ])->assertOk()->assertJsonPath('data.address_line', 'House 2, Road 3, Dhaka');

        $auth->deleteJson("/api/v1/account/addresses/{$addressId}")->assertOk();
        $auth->getJson('/api/v1/account/addresses')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_customer_cannot_update_or_delete_another_customers_address(): void
    {
        $token = $this->registerAndGetToken();
        $store = Store::whereNotNull('id')->firstOrFail();
        $otherCustomer = Customer::factory()->for($store)->create();
        $otherAddress = $otherCustomer->addresses()->create([
            'recipient_name' => 'Someone Else', 'phone' => '01799999999', 'address_line' => 'Elsewhere',
        ]);

        $auth = $this->withHeader('Authorization', "Bearer {$token}");

        $auth->putJson("/api/v1/account/addresses/{$otherAddress->id}", [
            'recipient_name' => 'Hijacked', 'phone' => '01712345678', 'address_line' => 'Nowhere',
        ])->assertStatus(404);

        $auth->deleteJson("/api/v1/account/addresses/{$otherAddress->id}")->assertStatus(404);

        $this->assertDatabaseHas('customer_addresses', ['id' => $otherAddress->id, 'recipient_name' => 'Someone Else']);
    }
}
