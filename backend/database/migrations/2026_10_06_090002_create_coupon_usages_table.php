<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            // nullOnDelete (not cascade) + a denormalized `code` snapshot:
            // deleting a coupon definition must not erase the historical
            // record of what a past order was actually discounted, the same
            // snapshot-survives-the-parent reasoning as order_items.unit_price_amount.
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('discount_amount');
            $table->char('currency_code', 3)->default('BDT');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['coupon_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
