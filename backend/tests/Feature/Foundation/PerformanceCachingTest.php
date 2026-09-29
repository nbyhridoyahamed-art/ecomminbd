<?php

namespace Tests\Feature\Foundation;

use App\Models\Category;
use App\Models\HomepageBlock;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function admin(Store $store): User
    {
        $user = User::factory()->create(['current_store_id' => $store->id]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_the_storefront_category_tree_is_served_from_cache_on_the_second_request(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Original Name']);

        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Original Name');

        // Bypasses the model entirely (no `saved` event, so the observer
        // never fires) — the only way the next read could see this is if
        // it isn't actually being served from cache.
        Category::withoutEvents(fn () => $category->update(['name' => 'Changed Directly In The Database']));

        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Original Name');
    }

    /**
     * Every other test here runs under the test suite's own `array` cache
     * driver (phpunit.xml), which holds a cached value as a live PHP
     * object in memory and never actually serializes it — so it would
     * never have caught the real bug this test is guarding against: a
     * cached Eloquent Collection/Resource round-trips fine through
     * `artisan tinker` but comes back as an unusable
     * `__PHP_Incomplete_Class` when read back through a real request
     * (confirmed live against `php artisan serve` with `CACHE_STORE=
     * database`, not assumed). Forcing the `file` driver here — the
     * simplest store that actually calls serialize()/unserialize() — is
     * what makes this test able to catch a regression back to caching
     * raw models/Resources instead of a plain resolved array.
     */
    public function test_the_storefront_category_cache_survives_real_serialization(): void
    {
        config(['cache.default' => 'file']);

        $store = Store::factory()->create(['status' => 'active']);
        Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Serialized Category']);

        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Serialized Category');

        // The real round-trip: read again from the now-populated file cache.
        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Serialized Category');
    }

    /** @see test_the_storefront_category_cache_survives_real_serialization for why `file` matters here. */
    public function test_the_storefront_homepage_cache_survives_real_serialization_for_a_model_backed_block(): void
    {
        config(['cache.default' => 'file']);

        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Serialized Category']);
        // category_grid's resolveBlockData() returns CategoryResource::collection(...)
        // — a live Resource wrapping a real model, exactly what broke.
        HomepageBlock::factory()->for($store)->ofType('category_grid')->create([
            'is_active' => true,
            'settings' => ['heading' => 'Shop', 'mode' => 'manual', 'limit' => 6, 'category_ids' => [$category->id]],
        ]);

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.data.categories.0.name', 'Serialized Category');

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.data.categories.0.name', 'Serialized Category');
    }

    public function test_updating_a_category_invalidates_the_storefront_cache(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Original Name']);
        $admin = $this->admin($store);

        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Original Name');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/categories/{$category->id}", [
                'store_id' => $store->id,
                'name' => 'Updated Name',
                'slug' => $category->slug,
            ])
            ->assertOk();

        $this->getJson('/api/v1/storefront/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Updated Name');
    }

    public function test_the_storefront_homepage_is_served_from_cache_on_the_second_request(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create([
            'is_active' => true,
            'settings' => ['content' => 'Original content'],
        ]);

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.settings.content', 'Original content');

        HomepageBlock::withoutEvents(fn () => $block->update(['settings' => ['content' => 'Changed directly']]));

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.settings.content', 'Original content');
    }

    public function test_publishing_a_homepage_block_invalidates_the_storefront_cache(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create([
            'is_active' => false,
            'settings' => ['content' => 'Draft content'],
        ]);
        $admin = $this->admin($store);

        $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$block->id}/publish")
            ->assertOk();

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.settings.content', 'Draft content');
    }

    public function test_reordering_homepage_blocks_invalidates_the_storefront_cache(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $first = HomepageBlock::factory()->for($store)->ofType('rich_text')->create(['is_active' => true, 'sort_order' => 0]);
        $second = HomepageBlock::factory()->for($store)->ofType('rich_text')->create(['is_active' => true, 'sort_order' => 1]);
        $admin = $this->admin($store);

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/homepage-blocks/reorder', ['order' => [$second->id, $first->id]])
            ->assertOk();

        $this->getJson('/api/v1/storefront/homepage-blocks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);
    }
}
