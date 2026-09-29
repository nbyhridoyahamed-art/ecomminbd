<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            // pending (created, nothing moved yet) -> in_transit (shipped,
            // source decremented) -> received (dest incremented); or
            // pending -> cancelled. See StockTransferController.
            $table->string('status')->default('pending')->after('to_warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
