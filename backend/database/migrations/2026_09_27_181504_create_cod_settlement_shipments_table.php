<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_settlement_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_settlement_id')->constrained('cod_settlements')->cascadeOnDelete();
            // unique, not just indexed — a shipment can be settled at most once,
            // enforced at the DB level as well as by the cod_settled flag it sets.
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_settlement_shipments');
    }
};
