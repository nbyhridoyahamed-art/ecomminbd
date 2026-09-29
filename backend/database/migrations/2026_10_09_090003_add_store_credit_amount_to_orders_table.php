<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Store credit redeemed against this order, resolved server-side
            // by StoreCreditResolver (clamped to the customer's balance and
            // the order's total) — see Order::totalAmount().
            $table->bigInteger('store_credit_amount')->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('store_credit_amount');
        });
    }
};
