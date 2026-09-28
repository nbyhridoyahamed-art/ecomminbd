<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Lets staff tell a self-service storefront order apart from one
            // they typed in themselves (e.g. a phone/COD order) in the admin
            // list — see StorefrontCheckoutController vs. OrderController::store().
            $table->string('source')->default('admin')->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
