<?php

namespace Tests\Feature\Storefront;

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_view_event_is_recorded(): void
    {
        $store = Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'page_view',
            'path' => '/products/some-slug',
        ])->assertCreated();

        $this->assertDatabaseHas('analytics_events', [
            'store_id' => $store->id,
            'session_id' => 'sess-1',
            'event_type' => 'page_view',
            'path' => '/products/some-slug',
        ]);
    }

    public function test_a_product_view_event_resolves_entity_type_and_id_from_a_validated_product_id(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $product = Product::factory()->for($store)->create();

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'product_view',
            'product_id' => $product->id,
        ])->assertCreated();

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'product_view',
            'entity_type' => Product::class,
            'entity_id' => $product->id,
        ]);
    }

    public function test_a_category_view_event_resolves_entity_type_and_id(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $category = Category::factory()->for($store)->create();

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'category_view',
            'category_id' => $category->id,
        ])->assertCreated();

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'category_view',
            'entity_type' => Category::class,
            'entity_id' => $category->id,
        ]);
    }

    public function test_a_nonexistent_product_id_is_rejected(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'product_view',
            'product_id' => 999999,
        ])->assertUnprocessable();
    }

    public function test_an_unknown_event_type_is_rejected(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'not_a_real_event',
        ])->assertUnprocessable();
    }

    public function test_a_missing_session_id_is_rejected(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'event_type' => 'page_view',
        ])->assertUnprocessable();
    }

    public function test_a_search_event_stores_the_query_and_results_count_in_metadata(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'search',
            'query' => 'panjabi',
            'results_count' => 3,
        ])->assertCreated();

        $event = AnalyticsEvent::where('event_type', 'search')->firstOrFail();
        $this->assertSame('panjabi', $event->metadata['query']);
        $this->assertSame(3, $event->metadata['results_count']);
    }

    /**
     * The whole point of resolving `total_amount` server-side: a client
     * cannot inflate reported revenue by lying about the amount, because
     * nothing it sends is ever used for the stored figure — only the real
     * order's own total, looked up by uuid, is.
     */
    public function test_a_purchase_event_records_the_real_order_total_not_a_client_supplied_one(): void
    {
        $store = Store::factory()->create(['status' => 'active']);
        $warehouse = Warehouse::factory()->for($store)->create();
        $customer = Customer::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $order = Order::factory()->for($store)->for($customer)->for($warehouse)->create();
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price_amount' => 50000]);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'purchase',
            'order_uuid' => $order->uuid,
        ])->assertCreated();

        $event = AnalyticsEvent::where('event_type', 'purchase')->firstOrFail();
        $this->assertSame($order->uuid, $event->metadata['order_uuid']);
        $this->assertEquals($order->fresh()->total_amount, $event->metadata['total_amount']);
    }

    public function test_a_purchase_event_with_no_matching_order_stores_no_metadata(): void
    {
        Store::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/storefront/analytics/events', [
            'session_id' => 'sess-1',
            'event_type' => 'purchase',
            'order_uuid' => (string) Str::uuid(),
        ])->assertCreated();

        $event = AnalyticsEvent::where('event_type', 'purchase')->firstOrFail();
        $this->assertNull($event->metadata);
    }
}
