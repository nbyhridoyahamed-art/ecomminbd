<?php

namespace Tests\Feature\Seo;

use App\Models\Product;
use App\Models\SeoTemplate;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTemplateTest extends TestCase
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

    public function test_a_user_without_seo_manage_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Warehouse Staff');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/seo-templates')
            ->assertForbidden();
    }

    public function test_a_template_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/seo-templates', [
            'store_id' => $store->id,
            'entity_type' => Product::class,
            'title_template' => '{{title}} | {{store_name}}',
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.entity_type', Product::class)
            ->assertJsonPath('data.title_template', '{{title}} | {{store_name}}');
        $templateId = $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/seo-templates/{$templateId}", [
                'store_id' => $store->id,
                'entity_type' => Product::class,
                'title_template' => '{{title}}',
                'description_template' => '{{excerpt}}',
            ])
            ->assertOk()
            ->assertJsonPath('data.description_template', '{{excerpt}}');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/seo-templates/{$templateId}")
            ->assertOk();

        $this->assertDatabaseMissing('seo_templates', ['id' => $templateId]);
    }

    public function test_entity_type_must_be_a_known_seo_bearing_model(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/seo-templates', [
                'store_id' => $store->id,
                'entity_type' => 'App\\Models\\Order',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entity_type');
    }

    public function test_entity_type_must_be_unique_per_store(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        SeoTemplate::factory()->for($store)->create(['entity_type' => Product::class]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/seo-templates', [
                'store_id' => $store->id,
                'entity_type' => Product::class,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entity_type');
    }

    public function test_the_same_entity_type_is_allowed_across_different_stores(): void
    {
        $admin = $this->admin();
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        SeoTemplate::factory()->for($storeA)->create(['entity_type' => Product::class]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/seo-templates', [
                'store_id' => $storeB->id,
                'entity_type' => Product::class,
            ])
            ->assertCreated();
    }
}
