<?php

namespace Tests\Feature\Storefront;

use App\Models\Page;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_pages_are_listed(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Page::factory()->for($store)->create(['title' => 'About Us', 'slug' => 'about-us', 'status' => 'published']);
        Page::factory()->for($store)->create(['title' => 'Draft Page', 'slug' => 'draft-page', 'status' => 'draft']);

        $response = $this->getJson('/api/v1/storefront/pages');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'about-us');
    }

    public function test_a_published_page_is_reachable_by_slug_without_authentication(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Page::factory()->for($store)->create([
            'title' => 'Terms & Conditions',
            'slug' => 'terms',
            'content' => 'These are the terms.',
            'status' => 'published',
        ]);

        $this->getJson('/api/v1/storefront/pages/terms')
            ->assertOk()
            ->assertJsonPath('data.title', 'Terms & Conditions')
            ->assertJsonPath('data.content', 'These are the terms.');
    }

    public function test_a_draft_page_404s_on_the_public_endpoint(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Page::factory()->for($store)->create(['slug' => 'coming-soon', 'status' => 'draft']);

        $this->getJson('/api/v1/storefront/pages/coming-soon')->assertNotFound();
    }

    public function test_a_page_belonging_to_a_different_store_is_not_reachable(): void
    {
        $activeStore = Store::factory()->create(['status' => 'active']);
        $otherStore = Store::factory()->create(['status' => 'inactive']);
        Page::factory()->for($otherStore)->create(['slug' => 'about-us', 'status' => 'published']);

        $this->getJson('/api/v1/storefront/pages/about-us')->assertNotFound();
    }
}
