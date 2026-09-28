<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Denormalized (also reachable via product_id -> products.store_id)
            // so the sku-uniqueness check and store-scoped queries don't need
            // a join — same reasoning as stock_movements.store_id.
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('sku');
            $table->string('barcode')->nullable();

            // Nullable overrides — null means "use the parent product's own
            // price," so most variants of a product don't need to repeat it.
            $table->bigInteger('price_amount')->nullable();
            $table->bigInteger('sale_price_amount')->nullable();
            $table->bigInteger('cost_price_amount')->nullable();

            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['store_id', 'sku']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
