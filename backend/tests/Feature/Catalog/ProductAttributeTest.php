<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAttributeTest extends TestCase
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

    public function test_an_attribute_can_be_created_with_values(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $attributeId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/product-attributes', ['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/product-attributes/{$attributeId}/values", ['value' => 'Red', 'slug' => 'red'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/product-attributes/{$attributeId}/values", ['value' => 'Blue', 'slug' => 'blue'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/product-attributes/{$attributeId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.values')
            ->assertJsonPath('data.values.0.value', 'Red');
    }

    public function test_a_value_can_be_updated_and_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Size', 'slug' => 'size']);
        $value = $attribute->values()->create(['value' => 'Small', 'slug' => 'small']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/product-attributes/{$attribute->id}/values/{$value->id}", ['value' => 'S', 'slug' => 's'])
            ->assertOk()
            ->assertJsonPath('data.value', 'S');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/product-attributes/{$attribute->id}/values/{$value->id}")
            ->assertOk();

        $this->assertDatabaseMissing('product_attribute_values', ['id' => $value->id]);
    }

    public function test_a_value_used_by_a_variant_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $product = Product::factory()->for($store)->create(['type' => 'variable']);
        $attribute = ProductAttribute::create(['store_id' => $store->id, 'name' => 'Color', 'slug' => 'color']);
        $value = $attribute->values()->create(['value' => 'Red', 'slug' => 'red']);

        $variant = ProductVariant::create([
            'store_id' => $store->id, 'product_id' => $product->id, 'sku' => 'SKU-RED', 'status' => 'active',
        ]);
        $variant->attributeValues()->attach($value->id);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/product-attributes/{$attribute->id}/values/{$value->id}")
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/product-attributes/{$attribute->id}")
            ->assertStatus(422);
    }

    public function test_a_user_without_attributes_view_is_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Order Manager');

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/product-attributes')
            ->assertForbidden();
    }
}
