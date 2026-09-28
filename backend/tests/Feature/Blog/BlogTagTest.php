<?php

namespace Tests\Feature\Blog;

use App\Models\BlogTag;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTagTest extends TestCase
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

    public function test_a_user_with_blog_manage_can_list_tags(): void
    {
        $store = Store::factory()->create();
        BlogTag::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/blog-tags?store_id='.$store->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_blog_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/blog-tags')
            ->assertForbidden();
    }

    public function test_a_tag_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-tags', [
            'store_id' => $store->id,
            'name' => 'Eid',
            'slug' => 'eid',
        ])->assertCreated();
        $tagId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/blog-tags/{$tagId}", [
                'store_id' => $store->id,
                'name' => 'Eid Collection',
                'slug' => 'eid',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Eid Collection');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/blog-tags/{$tagId}")
            ->assertOk();

        $this->assertSoftDeleted(BlogTag::class, ['id' => $tagId]);
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        BlogTag::factory()->for($store)->create(['slug' => 'eid']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/blog-tags', [
                'store_id' => $store->id,
                'name' => 'Duplicate',
                'slug' => 'eid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
