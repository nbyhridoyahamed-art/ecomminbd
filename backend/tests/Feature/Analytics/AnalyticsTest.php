<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    private function eventOn(Store $store, string $eventType, string $sessionId, string $createdAt, array $attributes = []): AnalyticsEvent
    {
        $event = AnalyticsEvent::factory()->for($store)->create(array_merge([
            'event_type' => $eventType,
            'session_id' => $sessionId,
        ], $attributes));
        $event->forceFill(['created_at' => $createdAt])->save();

        return $event;
    }

    public function test_overview_requires_analytics_permission(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/analytics/overview?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertForbidden();
    }

    public function test_overview_computes_totals_by_period_and_conversion_rate(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->eventOn($store, 'page_view', 'sess-1', '2026-06-10 09:00:00');
        $this->eventOn($store, 'page_view', 'sess-1', '2026-06-10 09:05:00');
        $this->eventOn($store, 'page_view', 'sess-2', '2026-06-10 10:00:00');
        $this->eventOn($store, 'product_view', 'sess-1', '2026-06-10 09:06:00');
        $this->eventOn($store, 'purchase', 'sess-1', '2026-06-10 09:10:00');
        // A different day — folds into its own by_period bucket.
        $this->eventOn($store, 'page_view', 'sess-3', '2026-06-11 09:00:00');
        // Outside the requested range — must be excluded.
        $this->eventOn($store, 'page_view', 'sess-4', '2026-07-01 09:00:00');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/overview?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $response->assertJsonPath('data.totals.page_views', 4)
            ->assertJsonPath('data.totals.unique_sessions', 3)
            ->assertJsonPath('data.totals.product_views', 1)
            ->assertJsonPath('data.totals.purchases', 1);

        // 1 purchase / 3 unique sessions = 33.33%.
        $this->assertEquals(33.33, $response->json('data.totals.conversion_rate'));

        $byPeriod = collect($response->json('data.by_period'))->keyBy('date');
        $this->assertSame(3, $byPeriod['2026-06-10']['page_views']);
        $this->assertSame(2, $byPeriod['2026-06-10']['unique_sessions']);
        $this->assertSame(1, $byPeriod['2026-06-11']['page_views']);
    }

    public function test_overview_comparison_reflects_the_immediately_preceding_period_of_equal_length(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        // A single-day primary range — its "previous period of equal length" is exactly the day before.
        $this->eventOn($store, 'page_view', 'sess-1', '2026-06-10 09:00:00');
        $this->eventOn($store, 'page_view', 'sess-2', '2026-06-10 10:00:00');
        $this->eventOn($store, 'page_view', 'sess-3', '2026-06-09 09:00:00');
        // Two days before — must not leak into the single-day comparison window.
        $this->eventOn($store, 'page_view', 'sess-4', '2026-06-08 09:00:00');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/overview?store_id={$store->id}&date_from=2026-06-10&date_to=2026-06-10")
            ->assertOk();

        $response->assertJsonPath('data.totals.page_views', 2)
            ->assertJsonPath('data.comparison.date_from', '2026-06-09')
            ->assertJsonPath('data.comparison.date_to', '2026-06-09')
            ->assertJsonPath('data.comparison.totals.page_views', 1);
    }

    public function test_overview_export_streams_a_csv_and_a_pdf(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $this->eventOn($store, 'page_view', 'sess-1', '2026-06-10 09:00:00');

        $csv = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/analytics/overview/export?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30");
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $this->assertStringContainsString('2026-06-10', $csv->streamedContent());

        $pdf = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/analytics/overview/export-pdf?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30");
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_products_report_ranks_by_views_and_computes_cart_rate(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $popular = Product::factory()->for($store)->create(['name' => 'Popular Product']);
        $quiet = Product::factory()->for($store)->create(['name' => 'Quiet Product']);

        $this->eventOn($store, 'product_view', 'sess-1', '2026-06-10 09:00:00', ['entity_type' => Product::class, 'entity_id' => $popular->id]);
        $this->eventOn($store, 'product_view', 'sess-2', '2026-06-10 09:01:00', ['entity_type' => Product::class, 'entity_id' => $popular->id]);
        $this->eventOn($store, 'add_to_cart', 'sess-1', '2026-06-10 09:02:00', ['entity_type' => Product::class, 'entity_id' => $popular->id]);
        $this->eventOn($store, 'product_view', 'sess-3', '2026-06-10 09:03:00', ['entity_type' => Product::class, 'entity_id' => $quiet->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/products?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('product_id');
        $this->assertSame(2, $rows[$popular->id]['view_count']);
        $this->assertSame(1, $rows[$popular->id]['add_to_cart_count']);
        $this->assertEquals(50.0, $rows[$popular->id]['view_to_cart_rate']);
        $this->assertSame(1, $rows[$quiet->id]['view_count']);
        $this->assertSame(0, $rows[$quiet->id]['add_to_cart_count']);

        // Ranked by view_count descending — the popular product comes first.
        $this->assertSame($popular->id, $response->json('data.0.product_id'));
    }

    public function test_searches_report_groups_by_query_case_insensitively_and_flags_zero_results(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        $this->eventOn($store, 'search', 'sess-1', '2026-06-10 09:00:00', ['metadata' => ['query' => 'Panjabi', 'results_count' => 5]]);
        $this->eventOn($store, 'search', 'sess-2', '2026-06-10 10:00:00', ['metadata' => ['query' => 'panjabi', 'results_count' => 3]]);
        $this->eventOn($store, 'search', 'sess-3', '2026-06-10 11:00:00', ['metadata' => ['query' => 'winter jacket', 'results_count' => 0]]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/searches?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $rows = collect($response->json('data'))->keyBy('query');
        $this->assertSame(2, $rows['panjabi']['search_count']);
        $this->assertEquals(4.0, $rows['panjabi']['avg_results_count']);
        $this->assertFalse($rows['panjabi']['zero_results']);

        $this->assertSame(1, $rows['winter jacket']['search_count']);
        $this->assertTrue($rows['winter jacket']['zero_results']);
    }

    public function test_funnel_reports_unique_sessions_per_stage_with_conversion_between_stages(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();

        // 2 sessions view a product; only 1 of those adds to cart and checks out; none purchase.
        $this->eventOn($store, 'product_view', 'sess-1', '2026-06-10 09:00:00');
        $this->eventOn($store, 'product_view', 'sess-2', '2026-06-10 09:01:00');
        $this->eventOn($store, 'add_to_cart', 'sess-1', '2026-06-10 09:02:00');
        $this->eventOn($store, 'checkout_start', 'sess-1', '2026-06-10 09:03:00');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/funnel?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $stages = collect($response->json('data.stages'))->keyBy('stage');
        $this->assertSame(2, $stages['product_view']['sessions']);
        $this->assertNull($stages['product_view']['conversion_from_previous']);
        $this->assertSame(1, $stages['add_to_cart']['sessions']);
        $this->assertEquals(50.0, $stages['add_to_cart']['conversion_from_previous']);
        $this->assertSame(1, $stages['checkout_start']['sessions']);
        $this->assertSame(0, $stages['purchase']['sessions']);
        $this->assertEquals(0.0, $stages['purchase']['conversion_from_previous']);
    }

    public function test_customers_report_classifies_new_vs_returning_and_computes_repeat_purchase_rate(): void
    {
        $admin = $this->admin();
        $store = Store::factory()->create();
        $warehouse = Warehouse::factory()->for($store)->create();
        $product = Product::factory()->for($store)->create();
        $newCustomer = Customer::factory()->for($store)->create();
        $repeatCustomer = Customer::factory()->for($store)->create();

        // repeatCustomer's very first order was before the report range.
        $firstOrder = Order::factory()->for($store)->for($repeatCustomer)->for($warehouse)->create(['status' => 'delivered']);
        $firstOrder->forceFill(['created_at' => '2026-05-01 09:00:00'])->save();

        // Both customers order again inside the reported range.
        $newOrder = Order::factory()->for($store)->for($newCustomer)->for($warehouse)->create(['status' => 'delivered']);
        $newOrder->forceFill(['created_at' => '2026-06-10 09:00:00'])->save();
        $returningOrder = Order::factory()->for($store)->for($repeatCustomer)->for($warehouse)->create(['status' => 'delivered']);
        $returningOrder->forceFill(['created_at' => '2026-06-10 10:00:00'])->save();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/analytics/customers?store_id={$store->id}&date_from=2026-06-01&date_to=2026-06-30")
            ->assertOk();

        $response->assertJsonPath('data.totals.new_customers', 1)
            ->assertJsonPath('data.totals.returning_customers', 1);

        // Only repeatCustomer has 2+ orders, out of 2 distinct customers ever — 50%.
        $this->assertEquals(50.0, $response->json('data.totals.repeat_purchase_rate'));
    }
}
