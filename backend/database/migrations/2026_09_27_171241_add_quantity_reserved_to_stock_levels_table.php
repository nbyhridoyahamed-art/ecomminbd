<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            // Reserved by pending/processing orders — see Phase 8's OrderController.
            // Available-to-sell = quantity - quantity_reserved.
            $table->unsignedInteger('quantity_reserved')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn('quantity_reserved');
        });
    }
};
