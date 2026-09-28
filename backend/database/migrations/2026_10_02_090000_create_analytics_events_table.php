<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Behavioral/traffic data — page views, product/category views, search
     * queries, cart and checkout funnel steps — distinct from Phase 18's
     * Reporting, which is pure read-side aggregation over already-existing
     * transactional tables (orders/order_items/stock_levels). Nothing here
     * duplicates that: "new vs returning customers" (section 1u below)
     * still reads straight from `orders`/`customers`, no event needed.
     * `entity_type`/`entity_id` reuses the same polymorphic convention
     * `activity_logs`/`seo_metadata` already use, but only ever set
     * server-side from a validated `product_id`/`category_id` — a public,
     * unauthenticated endpoint writes this table, so nothing here ever
     * stores a raw client-supplied class name or an unverified amount (see
     * `metadata`'s own note).
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // Client-generated, cookie/localStorage-persisted, anonymous by
            // design — a storefront visitor is tracked without an account.
            $table->string('session_id');
            $table->string('event_type');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('path', 500)->nullable();
            // Event-specific extras (search query/result count, the
            // add-to-cart line's product name/price snapshot). For
            // `purchase` specifically, `total_amount` here is always looked
            // up server-side from the real `orders` row by `order_uuid` —
            // see Storefront\AnalyticsController::track() — never a value
            // the client claims, so this column is never a spoofable
            // revenue source the way a client-fired pixel normally would be.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'event_type', 'created_at']);
            $table->index(['store_id', 'session_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
