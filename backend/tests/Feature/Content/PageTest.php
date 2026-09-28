<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
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

    public function test_a_user_with_pages_manage_can_list_pages(): void
    {
        $store = Store::factory()->create();
        Page::factory()->for($store)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/pages')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_pages_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/pages')
            ->assertForbidden();
    }

    public function test_the_content_manager_role_can_manage_pages(): void
    {
        $store = Store::factory()->create();
        $contentManager = User::factory()->create();
        $contentManager->assignRole('Content Manager');

        $this->actingAs($contentManager, 'sanctum')->postJson('/api/v1/pages', [
            'store_id' => $store->id,
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => 'We sell things.',
        ])->assertCreated();
    }

    public function test_a_page_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/pages', [
            'store_id' => $store->id,
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => 'We sell things.',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.status', 'draft');
        $pageId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/pages/{$pageId}", [
                'store_id' => $store->id,
                'title' => 'About Our Store',
                'slug' => 'about-us',
                'content' => 'We sell many things.',
                'status' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'About Our Store')
            ->assertJsonPath('data.status', 'published');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/pages/{$pageId}")
            ->assertOk();

        $this->assertSoftDeleted(Page::class, ['id' => $pageId]);
    }

    public function test_slug_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        Page::factory()->for($store)->create(['slug' => 'about-us']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/pages', [
                'store_id' => $store->id,
                'title' => 'Duplicate',
                'slug' => 'about-us',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_the_same_slug_is_allowed_across_different_stores(): void
    {
        $admin = $this->admin();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        Page::factory()->for($storeA)->create(['slug' => 'about-us']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/pages', [
                'store_id' => $storeB->id,
                'title' => 'About Us',
                'slug' => 'about-us',
            ])
            ->assertCreated();
    }

    public function test_slug_must_be_lowercase_letters_numbers_and_hyphens(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/pages', [
                'store_id' => $store->id,
                'title' => 'About Us',
                'slug' => 'About Us!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
