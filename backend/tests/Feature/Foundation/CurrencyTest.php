<?php

namespace Tests\Feature\Foundation;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_list_active_currencies(): void
    {
        Currency::factory()->create(['code' => 'BDT', 'status' => 'active']);
        Currency::factory()->create(['code' => 'USD', 'status' => 'inactive']);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/currencies');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BDT');
    }

    public function test_guests_cannot_list_currencies(): void
    {
        $this->getJson('/api/v1/currencies')->assertUnauthorized();
    }
}
