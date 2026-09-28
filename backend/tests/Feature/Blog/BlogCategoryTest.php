<?php

namespace Tests\Feature\Blog;

use App\Models\BlogCategory;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCategoryTest extends TestCase
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

    public function test_a_user_with_blog_manage_can_list_categories(): void
    {
        $store = Store::factory()->create();
        BlogCategory::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/blog-categories?store_id='.$store->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_blog_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/blog-categories')
            ->assertForbidden();
    }

    public function test_a_category_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/blog-categories', [
            'store_id' => $store->id,
            'name' => 'Announcements',
            'slug' => 'announcements',
        ])->assertCreated();
        $categoryId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/blog-categories/{$categoryId}", [
                'store_id' => $store->id,
                'name' => 'News & Announcements',
                'slug' => 'announcements',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'News & Announcements');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/blog-categories/{$categoryId}")
            ->assertOk();

        $this->assertSoftDeleted(BlogCategory::class, ['id' => $categoryId]);
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        BlogCategory::factory()->for($store)->create(['slug' => 'news']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/blog-categories', [
                'store_id' => $store->id,
                'name' => 'Duplicate',
                'slug' => 'news',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
