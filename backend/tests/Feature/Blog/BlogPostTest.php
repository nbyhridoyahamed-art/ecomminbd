<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPostTest extends TestCase
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

    public function test_a_user_with_blog_manage_can_list_posts(): void
    {
        $store = Store::factory()->create();
        BlogPost::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/blog-posts?store_id='.$store->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_blog_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/blog-posts')
            ->assertForbidden();
    }

    public function test_a_post_without_a_category_serializes_it_as_null_not_an_empty_object(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/blog-posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.tags', []);
    }

    public function test_a_post_can_be_created_with_a_category_and_tags(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $category = BlogCategory::factory()->for($store)->create();
        $tags = BlogTag::factory()->for($store)->count(2)->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'How to Choose the Right Panjabi',
            'slug' => 'how-to-choose-the-right-panjabi',
            'body' => '<p>Some content.</p>',
            'blog_category_id' => $category->id,
            'tag_ids' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseCount('blog_post_tag', 2);
    }

    public function test_marking_a_post_published_without_a_date_stamps_now(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Live Post',
            'slug' => 'live-post',
            'status' => 'published',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($response->json('data.published_at'));
    }

    public function test_a_scheduled_future_published_at_is_respected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $future = now()->addWeek()->startOfSecond();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Scheduled Post',
            'slug' => 'scheduled-post',
            'status' => 'published',
            'published_at' => $future->toIso8601String(),
        ]);

        $response->assertCreated();
        $this->assertTrue($future->equalTo($response->json('data.published_at')));
    }

    public function test_updating_tag_ids_resyncs_the_pivot(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create();
        $oldTag = BlogTag::factory()->for($store)->create();
        $newTag = BlogTag::factory()->for($store)->create();
        $post->tags()->attach($oldTag);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/blog-posts/{$post->id}", [
            'store_id' => $store->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'tag_ids' => [$newTag->id],
        ])->assertOk()->assertJsonCount(1, 'data.tags')->assertJsonPath('data.tags.0.id', $newTag->id);

        $this->assertDatabaseCount('blog_post_tag', 1);
    }

    public function test_a_category_from_another_store_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $category = BlogCategory::factory()->for($otherStore)->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Nope',
            'slug' => 'nope',
            'blog_category_id' => $category->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('blog_category_id');
    }

    public function test_a_tag_from_another_store_is_rejected(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $tag = BlogTag::factory()->for($otherStore)->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Nope',
            'slug' => 'nope-2',
            'tag_ids' => [$tag->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        BlogPost::factory()->for($store)->create(['slug' => 'existing']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-posts', [
            'store_id' => $store->id,
            'title' => 'Duplicate',
            'slug' => 'existing',
        ])->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_a_post_can_be_deleted_and_is_soft_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $post = BlogPost::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/blog-posts/{$post->id}")->assertOk();

        $this->assertSoftDeleted(BlogPost::class, ['id' => $post->id]);
    }
}
