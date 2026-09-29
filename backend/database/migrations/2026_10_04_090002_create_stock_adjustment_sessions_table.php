<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('reference');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Groups a stocktake's many stock_movements rows under one
            // reference (movements point back via the existing generic
            // reference_type/reference_id columns — no new FK needed there).
            // No status/soft-deletes: a session is created complete in one
            // shot and is an audit record from then on, the same
            // append-only-ledger reasoning stock_movements itself follows.
            $table->unique(['store_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_sessions');
    }
};
