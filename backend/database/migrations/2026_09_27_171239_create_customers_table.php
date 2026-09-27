<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            // Phone, not email, is the primary contact channel for BD customers
            // (COD orders are routinely phone-only) — see spec section 9.
            $table->string('phone');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
