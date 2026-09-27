<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
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

    public function test_a_user_with_categories_view_can_list_categories(): void
    {
        $store = Store::factory()->create();
        Category::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_categories_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/categories')
            ->assertForbidden();
    }

    public function test_a_category_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/categories', [
            'store_id' => $store->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);
        $create->assertCreated()->assertJsonPath('data.slug', 'electronics');
        $categoryId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/categories/{$categoryId}", [
                'store_id' => $store->id,
                'name' => 'Consumer Electronics',
                'slug' => 'electronics',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Consumer Electronics');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/categories/{$categoryId}")
            ->assertOk();

        $this->assertSoftDeleted(Category::class, ['id' => $categoryId]);
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Category::factory()->for($store)->create(['slug' => 'electronics']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/categories', [
                'store_id' => $store->id,
                'name' => 'Duplicate',
                'slug' => 'electronics',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_the_same_slug_is_allowed_across_different_stores(): void
    {
        $admin = $this->admin();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        Category::factory()->for($storeA)->create(['slug' => 'electronics']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/categories', [
                'store_id' => $storeB->id,
                'name' => 'Electronics',
                'slug' => 'electronics',
            ])
            ->assertCreated();
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $category = Category::factory()->for($store)->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/categories/{$category->id}", [
                'store_id' => $store->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_category_cannot_become_a_descendant_of_its_own_child(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $parent = Category::factory()->for($store)->create();
        $child = Category::factory()->for($store)->create(['parent_id' => $parent->id]);

        // Trying to make parent's new parent be its own child -> cycle.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/categories/{$parent->id}", [
                'store_id' => $store->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'parent_id' => $child->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $parent = Category::factory()->for($store)->create();
        Category::factory()->for($store)->create(['parent_id' => $parent->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/categories/{$parent->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('categories', ['id' => $parent->id, 'deleted_at' => null]);
    }
}
