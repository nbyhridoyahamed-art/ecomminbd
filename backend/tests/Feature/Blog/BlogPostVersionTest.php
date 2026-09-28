<?php

namespace Tests\Feature\Blog;

use App\Models\BlogPost;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPostVersionTest extends TestCase
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

    public function test_updating_a_post_snapshots_its_previous_state(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create(['title' => 'Original Title']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/blog-posts/{$post->id}", [
            'store_id' => $store->id,
            'title' => 'Updated Title',
            'slug' => $post->slug,
        ])->assertOk();

        $this->assertDatabaseCount('blog_post_versions', 1);

        $versions = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/blog-posts/{$post->id}/versions")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame('Original Title', $versions->json('data.0.snapshot.title'));
    }

    public function test_a_version_can_be_restored(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create(['title' => 'Original Title', 'excerpt' => 'Original excerpt']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/blog-posts/{$post->id}", [
            'store_id' => $store->id,
            'title' => 'Changed Title',
            'slug' => $post->slug,
            'excerpt' => 'Changed excerpt',
        ])->assertOk();

        $version = $post->versions()->first();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/blog-posts/{$post->id}/versions/{$version->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.title', 'Original Title')
            ->assertJsonPath('data.excerpt', 'Original excerpt');

        // Restoring is itself a change worth being able to undo.
        $this->assertDatabaseCount('blog_post_versions', 2);
    }

    public function test_versions_are_forbidden_without_blog_manage(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/blog-posts/{$post->id}/versions")
            ->assertForbidden();
    }
}
