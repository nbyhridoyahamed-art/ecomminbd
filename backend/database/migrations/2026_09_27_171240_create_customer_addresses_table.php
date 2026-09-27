<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone');
            $table->text('address_line');
            $table->foreignId('bd_division_id')->nullable()->constrained('bd_divisions')->nullOnDelete();
            $table->foreignId('bd_district_id')->nullable()->constrained('bd_districts')->nullOnDelete();
            $table->foreignId('bd_upazila_id')->nullable()->constrained('bd_upazilas')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
