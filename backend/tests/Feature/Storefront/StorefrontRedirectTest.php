<?php

namespace Tests\Feature\Storefront;

use App\Models\Redirect;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_matching_redirect_is_found_and_increments_its_hit_count(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $redirect = Redirect::factory()->for($store)->create([
            'from_path' => '/old-page',
            'to_path' => '/new-page',
            'status_code' => 301,
        ]);

        $this->getJson('/api/v1/storefront/redirects/lookup?path=/old-page')
            ->assertOk()
            ->assertJsonPath('data.to_path', '/new-page')
            ->assertJsonPath('data.status_code', 301);

        $this->assertSame(1, $redirect->fresh()->hits_count);
    }

    public function test_no_matching_redirect_returns_not_found(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->getJson('/api/v1/storefront/redirects/lookup?path=/nothing-here')
            ->assertNotFound();
    }

    public function test_a_missing_path_parameter_is_rejected(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->getJson('/api/v1/storefront/redirects/lookup')
            ->assertUnprocessable();
    }

    public function test_a_redirect_from_another_store_does_not_match(): void
    {
        Store::factory()->create(['status' => 'active']);
        $otherStore = Store::factory()->create(['status' => 'inactive']);
        Redirect::factory()->for($otherStore)->create(['from_path' => '/old-page', 'to_path' => '/new-page']);

        $this->getJson('/api/v1/storefront/redirects/lookup?path=/old-page')
            ->assertNotFound();
    }
}
