<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The resolved stock-fulfillment record for an order item — what
     * actually gets reserved/decremented/restocked, as opposed to
     * order_items itself, which is what was sold. For a simple/variable
     * product these are the same product/variant/quantity as the order
     * item (a 1-row identity expansion); for a bundle they're its
     * component products, quantity multiplied by the bundle's own
     * quantity. Snapshotted once when the order item is created rather
     * than re-derived from bundle_items on every reserve/ship/return, so
     * editing a bundle's components later can't split a single order
     * between two different resolutions (reserving one set of components
     * and decrementing another). This also unifies every write path in
     * OrderController/ReturnController/ShipmentController into one
     * uniform "loop the item's components" shape, with no branching on
     * product type at the point of use — see DATABASE_DESIGN.md section
     * 1l for the full write-up.
     */
    public function up(): void
    {
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
    }
};
