<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('main'); // main, branch, pickup_point, temporary
            $table->string('manager_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address_line')->nullable();
            $table->foreignId('bd_division_id')->nullable()->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('bd_district_id')->nullable()->constrained('bd_districts')->nullOnDelete();
            $table->foreignId('bd_upazila_id')->nullable()->constrained('bd_upazilas')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
