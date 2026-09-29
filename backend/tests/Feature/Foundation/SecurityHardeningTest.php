<?php

namespace Tests\Feature\Foundation;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_global_api_throttle_returns_429_once_the_limit_is_exceeded(): void
    {
        // AppServiceProvider disables the real 60/min limit under the test
        // suite (every test shares one in-process array cache and IP, so
        // the real limit would bleed across unrelated tests) — this
        // re-registers it with a tiny, test-only limit so this one test can
        // prove throttle:api is actually wired up and enforced.
        RateLimiter::for('api', fn () => Limit::perMinute(2));

        $this->getJson('/api/v1/storefront/store')->assertStatus(404);
        $this->getJson('/api/v1/storefront/store')->assertStatus(404);
        $this->getJson('/api/v1/storefront/store')->assertStatus(429);
    }

    public function test_every_api_response_carries_the_security_headers(): void
    {
        $response = $this->getJson('/api/v1/storefront/store');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'");
    }

    public function test_an_unexpected_exception_does_not_leak_details_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);

        Route::middleware('api')->get('/__test/security-hardening-boom', function () {
            throw new \RuntimeException('super secret internal detail that must not leak');
        });

        $response = $this->getJson('/__test/security-hardening-boom');

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Something went wrong. Please try again later.');
        $response->assertDontSee('super secret internal detail', false);
        $response->assertDontSee('RuntimeException', false);
    }

    public function test_an_unexpected_exception_still_renders_with_debug_details_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);

        Route::middleware('api')->get('/__test/security-hardening-boom-debug', function () {
            throw new \RuntimeException('this should be visible with debug on');
        });

        $response = $this->getJson('/__test/security-hardening-boom-debug');

        $response->assertStatus(500);
        $response->assertSee('this should be visible with debug on', false);
    }
}
