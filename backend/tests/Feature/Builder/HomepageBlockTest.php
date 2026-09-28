<?php

namespace Tests\Feature\Builder;

use App\Console\Commands\PublishScheduledHomepageBlocks;
use App\Models\Category;
use App\Models\HomepageBlock;
use App\Models\HomepageBlockRevision;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageBlockTest extends TestCase
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

    public function test_a_user_with_builder_view_can_list_blocks(): void
    {
        $store = Store::factory()->create();
        HomepageBlock::factory()->for($store)->ofType('hero')->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/homepage-blocks?store_id={$store->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_preview_includes_draft_blocks_resolved_the_same_way_as_the_public_endpoint(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->for($store)->create(['status' => 'active']);
        HomepageBlock::factory()->for($store)->ofType('category_grid')->create([
            // draft — not active
            'settings' => ['heading' => 'Categories', 'mode' => 'manual', 'limit' => 6, 'category_ids' => [$category->id]],
        ]);

        $preview = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/homepage-blocks/preview?store_id={$store->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $preview);
        $this->assertFalse($preview[0]['is_active']);
        $this->assertSame($category->id, $preview[0]['data']['categories'][0]['id']);

        // The public endpoint must not show this same draft block.
        $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_user_without_builder_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/homepage-blocks')
            ->assertForbidden();
    }

    public function test_marketing_manager_can_create_and_edit_but_not_publish(): void
    {
        $store = Store::factory()->create();
        $marketing = User::factory()->create();
        $marketing->assignRole('Marketing Manager');

        $create = $this->actingAs($marketing, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'rich_text',
            'settings' => ['heading' => 'About', 'body' => 'We sell things.'],
        ])->assertCreated();

        $blockId = $create->json('data.id');

        $this->actingAs($marketing, 'sanctum')
            ->putJson("/api/v1/homepage-blocks/{$blockId}", ['settings' => ['heading' => 'About Us', 'body' => 'We sell many things.']])
            ->assertOk();

        $this->actingAs($marketing, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$blockId}/publish")
            ->assertForbidden();
    }

    public function test_content_manager_can_view_edit_and_publish(): void
    {
        $store = Store::factory()->create();
        $contentManager = User::factory()->create();
        $contentManager->assignRole('Content Manager');
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create();

        $this->actingAs($contentManager, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$block->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_a_new_block_is_always_created_as_a_draft(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'rich_text',
            'settings' => ['heading' => 'About', 'body' => 'Ignored is_active below.'],
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.is_active', false);
    }

    public function test_settings_are_validated_per_block_type(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        // hero requires settings.heading
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'hero',
            'settings' => ['subheading' => 'no heading here'],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.heading');

        // spacer requires settings.height_px as an integer within bounds
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'spacer',
            'settings' => ['height_px' => 1000],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.height_px');
    }

    public function test_manual_mode_requires_ids_and_rejects_ids_from_another_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $otherStoreCategory = Category::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'category_grid',
            'settings' => ['heading' => 'Categories', 'limit' => 6, 'mode' => 'manual', 'category_ids' => []],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.category_ids');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'category_grid',
            'settings' => ['heading' => 'Categories', 'limit' => 6, 'mode' => 'manual', 'category_ids' => [$otherStoreCategory->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('settings.category_ids.0');
    }

    public function test_flash_sale_sale_price_is_submitted_as_decimal_and_stored_as_minor_units(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/homepage-blocks', [
            'store_id' => $store->id,
            'type' => 'flash_sale',
            'settings' => [
                'heading' => 'Flash Sale',
                'ends_at' => now()->addDay()->toIso8601String(),
                'items' => [['product_id' => $product->id, 'sale_price' => 123.45]],
            ],
        ])->assertCreated();

        // The client sends a decimal — the same API-boundary convention
        // every other money field in the app uses (see
        // ProductController::preparePayload()) — and the controller
        // converts it to the minor-unit integer actually stored.
        $stored = HomepageBlock::findOrFail($response->json('data.id'));
        $this->assertSame(12345, $stored->settings['items'][0]['sale_price']);
    }

    public function test_type_cannot_be_changed_on_update(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('hero')->create();

        // The update endpoint has no `type` field at all — the request is
        // validated against the block's own existing type regardless of
        // what (if anything) the caller sends for it, and the controller
        // never writes it back, so it's a silent no-op rather than a 422.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/homepage-blocks/{$block->id}", [
                'type' => 'rich_text',
                'settings' => ['heading' => 'Still a hero'],
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'hero')
            ->assertJsonPath('data.settings.heading', 'Still a hero');

        $this->assertSame('hero', $block->fresh()->type);
    }

    public function test_a_block_can_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/homepage-blocks/{$block->id}")
            ->assertOk();

        $this->assertDatabaseMissing('homepage_blocks', ['id' => $block->id]);
    }

    public function test_blocks_can_be_reordered(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $admin->update(['current_store_id' => $store->id]);
        $first = HomepageBlock::factory()->for($store)->ofType('rich_text')->create(['sort_order' => 0]);
        $second = HomepageBlock::factory()->for($store)->ofType('rich_text')->create(['sort_order' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/homepage-blocks/reorder', ['order' => [$second->id, $first->id]])
            ->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_reorder_rejects_a_block_id_from_another_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $admin->update(['current_store_id' => $store->id]);
        $otherStoreBlock = HomepageBlock::factory()->ofType('rich_text')->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/homepage-blocks/reorder', ['order' => [$otherStoreBlock->id]])
            ->assertUnprocessable();
    }

    public function test_a_block_can_be_duplicated_as_a_draft_inserted_right_after_it(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $original = HomepageBlock::factory()->for($store)->ofType('rich_text')->active()->create(['sort_order' => 0]);
        $after = HomepageBlock::factory()->for($store)->ofType('rich_text')->active()->create(['sort_order' => 1]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$original->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.is_active', false);

        $duplicate = HomepageBlock::find($response->json('data.id'));
        $this->assertSame($original->sort_order + 1, $duplicate->sort_order);
        $this->assertSame($original->sort_order + 2, $after->fresh()->sort_order);
    }

    public function test_publish_and_unpublish_toggle_is_active_and_record_a_revision(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/homepage-blocks/{$block->id}/publish")->assertOk();
        $this->assertTrue($block->fresh()->is_active);

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/homepage-blocks/{$block->id}/unpublish")->assertOk();
        $this->assertFalse($block->fresh()->is_active);

        $this->assertSame(2, HomepageBlockRevision::where('homepage_block_id', $block->id)->count());
    }

    public function test_a_block_can_be_scheduled_and_the_command_publishes_it_once_due(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$block->id}/schedule", ['scheduled_at' => now()->addMinute()->toIso8601String()])
            ->assertOk();

        $this->artisan(PublishScheduledHomepageBlocks::class)->assertExitCode(0);
        $this->assertFalse($block->fresh()->is_active, 'should not publish before the scheduled time');

        $block->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan(PublishScheduledHomepageBlocks::class)->assertExitCode(0);

        $block->refresh();
        $this->assertTrue($block->is_active);
        $this->assertNull($block->scheduled_at);
    }

    public function test_editing_a_block_records_a_revision_that_can_be_restored(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('rich_text')->create([
            'settings' => ['heading' => 'Original', 'body' => 'Original body'],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/homepage-blocks/{$block->id}", ['settings' => ['heading' => 'Edited', 'body' => 'Edited body']])
            ->assertOk();

        $revisions = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/homepage-blocks/{$block->id}/revisions")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $revisions);
        $this->assertSame('Original', $revisions[0]['snapshot']['settings']['heading']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$block->id}/revisions/{$revisions[0]['id']}/restore")
            ->assertOk()
            ->assertJsonPath('data.settings.heading', 'Original');

        // Restoring is itself recorded, so it can be undone too.
        $this->assertSame(2, HomepageBlockRevision::where('homepage_block_id', $block->id)->count());
    }

    public function test_a_block_can_be_saved_as_a_reusable_section(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $block = HomepageBlock::factory()->for($store)->ofType('hero')->create([
            'settings' => ['heading' => 'Summer Campaign', 'subheading' => null, 'image_url' => null, 'cta_label' => null, 'cta_url' => null],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/homepage-blocks/{$block->id}/save-as-section", ['name' => 'Summer Campaign Hero'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Summer Campaign Hero')
            ->assertJsonPath('data.settings.heading', 'Summer Campaign');

        $this->assertDatabaseHas('saved_sections', ['store_id' => $store->id, 'name' => 'Summer Campaign Hero']);
    }
}
