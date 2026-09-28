<?php

namespace Tests\Feature\Storefront;

use App\Models\BdDivision;
use App\Models\Brand;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_store_identity_is_reachable_without_authentication(): void
    {
        Store::factory()->create(['status' => 'active', 'name' => 'Demo Store', 'slug' => 'demo-store']);

        $this->getJson('/api/v1/storefront/store')
            ->assertOk()
            ->assertJsonPath('data.name', 'Demo Store')
            ->assertJsonPath('data.slug', 'demo-store')
            ->assertJsonPath('data.currency_code', 'BDT');
    }

    public function test_product_listing_only_returns_active_products_from_the_active_store(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $otherStore = Store::factory()->create(['status' => 'inactive']);

        $visible = Product::factory()->for($store)->create(['status' => 'active', 'name' => 'Visible Product']);
        Product::factory()->for($store)->create(['status' => 'draft', 'name' => 'Draft Product']);
        // Belongs to a store that isn't active — must never leak through,
        // even though the product row itself is status=active.
        Product::factory()->for($otherStore)->create(['status' => 'active', 'name' => 'Other Store Product']);

        $response = $this->getJson('/api/v1/storefront/products');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertEqualsCanonicalizing(['Visible Product'], $names->all());
        $this->assertSame($visible->id, $response->json('data.0.id'));
    }

    public function test_it_returns_a_clear_response_when_no_active_store_exists(): void
    {
        Store::factory()->create(['status' => 'inactive']);

        $this->getJson('/api/v1/storefront/products')->assertNotFound();
    }

    public function test_product_listing_supports_search_category_brand_and_featured_filters(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create(['slug' => 'electronics']);
        $brand = Brand::factory()->for($store)->create(['slug' => 'acme']);

        $matchesSearch = Product::factory()->for($store)->create(['name' => 'Bluetooth Speaker', 'status' => 'active']);
        Product::factory()->for($store)->create(['name' => 'Kitchen Knife', 'status' => 'active']);

        $inCategory = Product::factory()->for($store)->create(['category_id' => $category->id, 'status' => 'active']);
        $inBrand = Product::factory()->for($store)->create(['brand_id' => $brand->id, 'status' => 'active']);
        $featured = Product::factory()->for($store)->create(['featured' => true, 'status' => 'active']);

        $this->getJson('/api/v1/storefront/products?search=speaker')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchesSearch->id);

        $this->getJson('/api/v1/storefront/products?category=electronics')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inCategory->id);

        $this->getJson('/api/v1/storefront/products?brand=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inBrand->id);

        $this->getJson('/api/v1/storefront/products?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $featured->id);
    }

    public function test_product_listing_never_exposes_internal_pricing_or_ownership_fields(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Product::factory()->for($store)->create(['status' => 'active', 'cost_price_amount' => 5000]);

        $response = $this->getJson('/api/v1/storefront/products')->assertOk();

        $response->assertJsonMissingPath('data.0.cost_price_amount')
            ->assertJsonMissingPath('data.0.cost_price')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.store_id');
    }

    public function test_product_listing_computes_in_stock_from_warehouse_stock_levels(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();

        $inStock = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($inStock)->for($warehouse)->create(['quantity' => 5, 'quantity_reserved' => 0]);

        $outOfStock = Product::factory()->for($store)->create(['status' => 'active']);
        StockLevel::factory()->for($outOfStock)->for($warehouse)->create(['quantity' => 3, 'quantity_reserved' => 3]);

        $response = $this->getJson('/api/v1/storefront/products')->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($byId[$inStock->id]['in_stock']);
        $this->assertFalse($byId[$outOfStock->id]['in_stock']);
    }

    public function test_bundle_in_stock_reflects_the_minimum_available_across_its_components(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();

        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'status' => 'active']);
        $component = Product::factory()->for($store)->create(['status' => 'active']);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $component->id, 'quantity' => 2]);
        StockLevel::factory()->for($component)->for($warehouse)->create(['quantity' => 1, 'quantity_reserved' => 0]);

        $response = $this->getJson('/api/v1/storefront/products')->assertOk();

        // Only 1 unit of a component that needs 2 per bundle -> 0 sellable.
        $this->assertFalse(collect($response->json('data'))->firstWhere('id', $bundle->id)['in_stock']);
    }

    public function test_product_detail_page_includes_variants_and_bundle_components(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();

        $bundle = Product::factory()->for($store)->create(['type' => 'bundle', 'status' => 'active', 'slug' => 'combo-pack']);
        $component = Product::factory()->for($store)->create(['status' => 'active', 'name' => 'Widget', 'cost_price_amount' => 999]);
        BundleItem::create(['bundle_product_id' => $bundle->id, 'component_product_id' => $component->id, 'quantity' => 3]);
        StockLevel::factory()->for($component)->for($warehouse)->create(['quantity' => 10, 'quantity_reserved' => 0]);

        $response = $this->getJson('/api/v1/storefront/products/combo-pack')->assertOk();

        $response->assertJsonPath('data.components.0.product_name', 'Widget')
            ->assertJsonPath('data.components.0.quantity', 3)
            ->assertJsonPath('data.bundle_availability.total_available', 3)
            ->assertJsonMissingPath('data.components.0.cost_price');
    }

    public function test_product_detail_page_404s_for_a_draft_or_missing_product(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        Product::factory()->for($store)->create(['status' => 'draft', 'slug' => 'hidden-product']);

        $this->getJson('/api/v1/storefront/products/hidden-product')->assertNotFound();
        $this->getJson('/api/v1/storefront/products/does-not-exist')->assertNotFound();
    }

    public function test_category_index_returns_only_top_level_active_categories_with_active_children(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $parent = Category::factory()->for($store)->create(['name' => 'Electronics', 'status' => 'active']);
        $child = Category::factory()->for($store)->create(['name' => 'Phones', 'status' => 'active', 'parent_id' => $parent->id]);
        Category::factory()->for($store)->create(['name' => 'Hidden Child', 'status' => 'inactive', 'parent_id' => $parent->id]);
        Category::factory()->for($store)->create(['name' => 'Inactive Top Level', 'status' => 'inactive']);

        $response = $this->getJson('/api/v1/storefront/categories')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Electronics')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.name', $child->name);
    }

    public function test_category_show_returns_the_category_and_its_active_products_paginated(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create(['slug' => 'phones']);
        $visible = Product::factory()->for($store)->create(['category_id' => $category->id, 'status' => 'active']);
        Product::factory()->for($store)->create(['category_id' => $category->id, 'status' => 'draft']);

        $response = $this->getJson('/api/v1/storefront/categories/phones')->assertOk();

        $response->assertJsonPath('data.category.slug', 'phones')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', $visible->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_brand_index_and_show_mirror_category_behaviour(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $brand = Brand::factory()->for($store)->create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active']);
        Brand::factory()->for($store)->create(['status' => 'inactive']);
        $visible = Product::factory()->for($store)->create(['brand_id' => $brand->id, 'status' => 'active']);
        Product::factory()->for($store)->create(['brand_id' => $brand->id, 'status' => 'draft']);

        $this->getJson('/api/v1/storefront/brands')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'acme');

        $response = $this->getJson('/api/v1/storefront/brands/acme')->assertOk();
        $response->assertJsonPath('data.brand.slug', 'acme')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', $visible->id);
    }

    public function test_the_bd_location_reference_data_is_reachable_without_authentication(): void
    {
        BdDivision::create(['name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'code' => '30']);

        $this->getJson('/api/v1/storefront/locations/divisions')
            ->assertOk()
            ->assertJsonPath('data.0.name_en', 'Dhaka');
    }
}
