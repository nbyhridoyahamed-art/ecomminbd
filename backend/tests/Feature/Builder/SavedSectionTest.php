<?php

namespace Tests\Feature\Builder;

use App\Models\SavedSection;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSectionTest extends TestCase
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

    public function test_a_saved_section_can_be_created_and_listed(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/saved-sections', [
            'store_id' => $store->id,
            'name' => 'Summer Campaign Hero',
            'type' => 'hero',
            'settings' => ['heading' => 'Summer Sale', 'subheading' => null, 'image_url' => null, 'cta_label' => null, 'cta_url' => null],
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/saved-sections?store_id={$store->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_inserting_a_saved_section_creates_a_draft_block_on_the_homepage(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $section = SavedSection::factory()->for($store)->create([
            'type' => 'hero',
            'settings' => ['heading' => 'Summer Sale', 'subheading' => null, 'image_url' => null, 'cta_label' => null, 'cta_url' => null],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/saved-sections/{$section->id}/insert", ['store_id' => $store->id])
            ->assertCreated()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.settings.heading', 'Summer Sale');

        $this->assertDatabaseHas('homepage_blocks', ['id' => $response->json('data.id'), 'store_id' => $store->id]);
    }

    public function test_a_saved_section_can_be_deleted(): void
    {
        $admin = $this->admin();
        $section = SavedSection::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/saved-sections/{$section->id}")
            ->assertOk();

        $this->assertDatabaseMissing('saved_sections', ['id' => $section->id]);
    }

    public function test_a_user_without_builder_edit_cannot_create_a_saved_section(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');
        $store = Store::factory()->create();

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/saved-sections', [
            'store_id' => $store->id,
            'name' => 'Nope',
            'type' => 'rich_text',
            'settings' => ['body' => 'x'],
        ])->assertForbidden();
    }
}
