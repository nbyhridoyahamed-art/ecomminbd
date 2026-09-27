<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->string('settlement_number');
            // Sum of the covered shipments' cod_amount_collected, computed once at
            // creation — a settlement is an immutable financial record (same
            // "append-only ledger" reasoning as stock_movements/order_status_history:
            // no update/destroy endpoint), so this never needs recomputing later.
            $table->bigInteger('amount_expected');
            // What the courier actually remitted — can differ from amount_expected
            // (courier fees, discrepancies); the gap is visible, not silently fixed.
            $table->bigInteger('amount_received');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'settlement_number']);
            $table->index('courier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_settlements');
    }
};
