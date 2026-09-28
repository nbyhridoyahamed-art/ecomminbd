<?php

namespace Tests\Feature\Storefront;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HomepageBlock;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Testimonial;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomepageTest extends TestCase
{
    use RefreshDatabase;

    private function activeStore(): Store
    {
        return Store::factory()->create(['status' => 'active']);
    }

    public function test_only_active_blocks_are_returned_in_sort_order(): void
    {
        $store = $this->activeStore();
        HomepageBlock::factory()->for($store)->ofType('rich_text')->active()->create(['sort_order' => 1, 'settings' => ['heading' => 'Second', 'body' => 'b']]);
        HomepageBlock::factory()->for($store)->ofType('rich_text')->active()->create(['sort_order' => 0, 'settings' => ['heading' => 'First', 'body' => 'a']]);
        HomepageBlock::factory()->for($store)->ofType('rich_text')->create(['sort_order' => 2, 'settings' => ['heading' => 'Draft', 'body' => 'c']]); // not active

        $response = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk();
        $blocks = $response->json('data');

        $this->assertCount(2, $blocks);
        $this->assertSame('First', $blocks[0]['settings']['heading']);
        $this->assertSame('Second', $blocks[1]['settings']['heading']);
    }

    public function test_a_static_block_returns_its_raw_settings_with_no_extra_resolution(): void
    {
        $store = $this->activeStore();
        HomepageBlock::factory()->for($store)->ofType('hero')->active()->create([
            'settings' => ['heading' => 'Welcome', 'subheading' => null, 'image_url' => null, 'cta_label' => 'Shop', 'cta_url' => '/products', 'secondary_cta_label' => null, 'secondary_cta_url' => null],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');

        $this->assertSame('Welcome', $blocks[0]['settings']['heading']);
        $this->assertSame([], $blocks[0]['data']);
    }

    public function test_category_grid_auto_mode_lists_top_level_categories(): void
    {
        $store = $this->activeStore();
        $parent = Category::factory()->for($store)->create(['status' => 'active']);
        Category::factory()->for($store)->create(['status' => 'active', 'parent_id' => $parent->id]); // not top-level
        HomepageBlock::factory()->for($store)->ofType('category_grid')->active()->create([
            'settings' => ['heading' => 'Shop by Category', 'mode' => 'auto', 'limit' => 6, 'category_ids' => []],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');

        $this->assertCount(1, $blocks[0]['data']['categories']);
        $this->assertSame($parent->id, $blocks[0]['data']['categories'][0]['id']);
    }

    public function test_category_grid_manual_mode_preserves_the_chosen_order(): void
    {
        $store = $this->activeStore();
        $first = Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Zeta']);
        $second = Category::factory()->for($store)->create(['status' => 'active', 'name' => 'Alpha']);
        HomepageBlock::factory()->for($store)->ofType('category_grid')->active()->create([
            'settings' => ['heading' => 'Picked', 'mode' => 'manual', 'limit' => 6, 'category_ids' => [$first->id, $second->id]],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');
        $categories = $blocks[0]['data']['categories'];

        $this->assertSame([$first->id, $second->id], array_column($categories, 'id'));
    }

    public function test_featured_products_only_returns_featured_active_products(): void
    {
        $store = $this->activeStore();
        Product::factory()->for($store)->create(['featured' => true, 'status' => 'active']);
        Product::factory()->for($store)->create(['featured' => false, 'status' => 'active']);
        HomepageBlock::factory()->for($store)->ofType('featured_products')->active()->create([
            'settings' => ['heading' => 'Featured', 'mode' => 'auto', 'limit' => 5, 'product_ids' => []],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');

        $this->assertCount(1, $blocks[0]['data']['products']);
    }

    public function test_product_carousel_category_mode_scopes_to_the_chosen_category(): void
    {
        $store = $this->activeStore();
        $category = Category::factory()->for($store)->create();
        $inCategory = Product::factory()->for($store)->create(['status' => 'active', 'category_id' => $category->id]);
        Product::factory()->for($store)->create(['status' => 'active']); // different category

        HomepageBlock::factory()->for($store)->ofType('product_carousel')->active()->create([
            'settings' => ['heading' => 'Related', 'mode' => 'category', 'limit' => 10, 'product_ids' => [], 'category_id' => $category->id],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');
        $products = $blocks[0]['data']['products'];

        $this->assertCount(1, $products);
        $this->assertSame($inCategory->id, $products[0]['id']);
    }

    public function test_best_sellers_ranks_products_by_units_sold_excluding_cancelled_orders(): void
    {
        $store = $this->activeStore();
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $topSeller = Product::factory()->for($store)->create(['status' => 'active', 'name' => 'Top Seller']);
        $runnerUp = Product::factory()->for($store)->create(['status' => 'active', 'name' => 'Runner Up']);
        $cancelledOnly = Product::factory()->for($store)->create(['status' => 'active', 'name' => 'Cancelled Only']);

        $this->orderItem($store, $warehouse, $customer, 'delivered', $topSeller, 5);
        $this->orderItem($store, $warehouse, $customer, 'delivered', $runnerUp, 2);
        $this->orderItem($store, $warehouse, $customer, 'cancelled', $cancelledOnly, 99);

        HomepageBlock::factory()->for($store)->ofType('best_sellers')->active()->create([
            'settings' => ['heading' => 'Best Sellers', 'limit' => 8],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');
        $names = array_column($blocks[0]['data']['products'], 'name');

        $this->assertSame(['Top Seller', 'Runner Up'], $names);
    }

    public function test_flash_sale_resolves_each_item_to_its_product_and_sale_price(): void
    {
        $store = $this->activeStore();
        $product = Product::factory()->for($store)->create(['status' => 'active']);

        HomepageBlock::factory()->for($store)->ofType('flash_sale')->active()->create([
            'settings' => ['heading' => 'Flash Sale', 'ends_at' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product->id, 'sale_price' => 12345]]],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');
        $item = $blocks[0]['data']['items'][0];

        $this->assertSame($product->id, $item['product']['id']);
        $this->assertSame(123.45, $item['sale_price']);
    }

    public function test_testimonials_and_reviews_resolve_active_testimonials_only(): void
    {
        $store = $this->activeStore();
        Testimonial::factory()->for($store)->create(['is_active' => true, 'sort_order' => 0]);
        Testimonial::factory()->for($store)->create(['is_active' => false, 'sort_order' => 1]);
        HomepageBlock::factory()->for($store)->ofType('testimonials')->active()->create([
            'settings' => ['heading' => 'Reviews', 'mode' => 'auto', 'limit' => 3, 'testimonial_ids' => []],
        ]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');

        $this->assertCount(1, $blocks[0]['data']['testimonials']);
    }

    public function test_blog_posts_excludes_unpublished_future_and_inactive_posts(): void
    {
        $store = $this->activeStore();
        BlogPost::factory()->for($store)->create(['published_at' => now()->subDay(), 'is_active' => true]);
        BlogPost::factory()->for($store)->create(['published_at' => now()->addDay(), 'is_active' => true]); // future
        BlogPost::factory()->for($store)->create(['published_at' => null, 'is_active' => true]); // never published
        BlogPost::factory()->for($store)->create(['published_at' => now()->subDay(), 'is_active' => false]); // inactive
        HomepageBlock::factory()->for($store)->ofType('blog_posts')->active()->create(['settings' => ['heading' => 'Blog', 'limit' => 5]]);

        $blocks = $this->getJson('/api/v1/storefront/homepage-blocks')->assertOk()->json('data');

        $this->assertCount(1, $blocks[0]['data']['posts']);
    }

    private function orderItem(Store $store, Warehouse $warehouse, Customer $customer, string $status, Product $product, int $quantity): void
    {
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create(['status' => $status]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price_amount' => 10000]);
    }
}
