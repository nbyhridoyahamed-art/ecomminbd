<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            // Both null = the store's fallback zone, matching any shipping
            // location no more specific zone covers. A district set without a
            // division is rejected by DeliveryZoneRequest, not the schema.
            $table->foreignId('bd_division_id')->nullable()->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('bd_district_id')->nullable()->constrained('bd_districts')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
