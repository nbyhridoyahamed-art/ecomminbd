<?php

namespace Tests\Feature\Account;

use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Karim Rahman',
            'phone' => '01712345678',
            'email' => 'karim@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_a_new_customer_can_register_and_receives_a_token(): void
    {
        Store::factory()->create(['status' => 'active']);

        $response = $this->postJson('/api/v1/account/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonPath('data.customer.name', 'Karim Rahman')
            ->assertJsonPath('data.customer.phone', '01712345678')
            ->assertJsonMissingPath('data.customer.password')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('customers', ['phone' => '01712345678', 'name' => 'Karim Rahman']);
        $customer = Customer::firstOrFail();
        $this->assertNotNull($customer->password);
        $this->assertTrue(Hash::check('password123', $customer->password));
    }

    public function test_registering_with_a_phone_that_matches_an_unclaimed_guest_checkout_claims_it(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $guest = Customer::factory()->for($store)->create([
            'phone' => '01712345678', 'name' => 'Guest Name', 'password' => null,
        ]);

        $response = $this->postJson('/api/v1/account/auth/register', $this->registerPayload(['name' => 'Karim Rahman']));

        $response->assertCreated();
        $this->assertSame(1, Customer::count());
        $guest->refresh();
        $this->assertSame('Karim Rahman', $guest->name);
        $this->assertNotNull($guest->password);
    }

    public function test_registering_with_a_phone_normalized_differently_still_claims_the_same_guest_record(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Customer::factory()->for($store)->create(['phone' => '01712345678', 'password' => null]);

        // +880 / spaced form — must normalize to the same 01XXXXXXXXX the
        // guest checkout stored, or the claim silently fails to link up.
        $this->postJson('/api/v1/account/auth/register', $this->registerPayload(['phone' => '+880 1712-345678']))
            ->assertCreated();

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['phone' => '01712345678']);
    }

    public function test_registering_with_an_already_claimed_phone_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Customer::factory()->for($store)->create(['phone' => '01712345678', 'password' => Hash::make('existing')]);

        $this->postJson('/api/v1/account/auth/register', $this->registerPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame(1, Customer::count());
    }

    public function test_a_registered_customer_can_log_in_with_phone_and_password(): void
    {
        Store::factory()->create(['status' => 'active']);
        $this->postJson('/api/v1/account/auth/register', $this->registerPayload())->assertCreated();

        $response = $this->postJson('/api/v1/account/auth/login', [
            'phone' => '01712345678',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_with_the_wrong_password_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Customer::factory()->for($store)->create(['phone' => '01712345678', 'password' => Hash::make('correct-password')]);

        $this->postJson('/api/v1/account/auth/login', ['phone' => '01712345678', 'password' => 'wrong-password'])
            ->assertStatus(422);
    }

    public function test_login_against_an_unclaimed_guest_checkout_phone_is_rejected(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Customer::factory()->for($store)->create(['phone' => '01712345678', 'password' => null]);

        $this->postJson('/api/v1/account/auth/login', ['phone' => '01712345678', 'password' => 'anything'])
            ->assertStatus(422);
    }

    public function test_me_and_logout_require_a_customer_token(): void
    {
        Store::factory()->create(['status' => 'active']);
        $token = $this->postJson('/api/v1/account/auth/register', $this->registerPayload())->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', '01712345678');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/account/auth/logout')
            ->assertOk();

        // Laravel's AuthManager caches a resolved guard per name for the
        // life of the container (AuthManager::guard()'s `??=`), so within
        // one test method a second request would otherwise see the first
        // call's cached RequestGuard user instead of re-resolving the
        // (now-deleted) token — forgetGuards() is the documented way to
        // simulate what a genuinely new incoming request does naturally.
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/auth/me')
            ->assertStatus(401);
    }

    public function test_a_customer_token_cannot_access_admin_routes(): void
    {
        Store::factory()->create(['status' => 'active']);
        $token = $this->postJson('/api/v1/account/auth/register', $this->registerPayload())->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/orders')
            ->assertStatus(401);
    }

    public function test_a_staff_token_cannot_access_account_routes(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/auth/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/account/orders')
            ->assertStatus(401);
    }
}
