<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // The delivered order this review proves a verified purchase
            // against — set once at submission time and never re-checked
            // later, the same "prove it once, snapshot it" reasoning
            // order_item_components uses for bundles: an order refunded or
            // returned after the review was written doesn't retroactively
            // invalidate a review that was genuinely earned when written.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            // pending -> approved|rejected. A customer's own GET sees all
            // three; the storefront only ever sees approved.
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();

            // One review per customer per product, regardless of how many
            // qualifying orders they have — matches how most storefronts
            // scope "your review", not "your review of this order".
            $table->unique(['product_id', 'customer_id']);
            $table->index(['store_id', 'product_id', 'status']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
