<?php

namespace Tests\Feature\Builder;

use App\Models\BlogPost;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialAndBlogPostTest extends TestCase
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

    public function test_testimonials_can_be_managed_under_builder_permissions(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/testimonials', [
            'store_id' => $store->id,
            'name' => 'Farhana Akter',
            'quote' => 'Great service!',
            'rating' => 5,
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/testimonials/'.$create->json('data.id'))
            ->assertOk();
    }

    public function test_a_user_without_builder_view_cannot_list_testimonials(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/testimonials')
            ->assertForbidden();
    }

    public function test_blog_posts_reuse_the_pre_existing_blog_manage_permission(): void
    {
        $store = Store::factory()->create();
        $seoManager = User::factory()->create();
        $seoManager->assignRole('SEO Manager');

        $this->actingAs($seoManager, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'How to Choose the Right Panjabi',
            'slug' => 'how-to-choose-the-right-panjabi',
            'excerpt' => 'A quick guide.',
        ])->assertCreated();
    }

    public function test_blog_post_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        BlogPost::factory()->for($store)->create(['slug' => 'existing-post']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Duplicate',
            'slug' => 'existing-post',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_a_user_without_blog_manage_cannot_create_a_blog_post(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Nope',
            'slug' => 'nope',
        ])->assertForbidden();
    }

    public function test_newsletter_subscribers_can_be_listed_and_removed(): void
    {
        $admin = $this->admin();
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/newsletter/subscribe', ['email' => 'shopper@example.com'])
            ->assertCreated();

        // Idempotent: subscribing again with the same email is still success, not a duplicate row.
        $this->postJson('/api/v1/storefront/newsletter/subscribe', ['email' => 'shopper@example.com'])
            ->assertCreated();
        $this->assertDatabaseCount('newsletter_subscribers', 1);

        $listed = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/newsletter-subscribers')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/newsletter-subscribers/'.$listed->json('data.0.id'))
            ->assertOk();

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
