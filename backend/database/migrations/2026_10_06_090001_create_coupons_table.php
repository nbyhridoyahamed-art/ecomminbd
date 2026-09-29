<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('discount_type'); // percentage | fixed
            $table->unsignedSmallInteger('percentage_value')->nullable();
            $table->bigInteger('fixed_discount_amount')->nullable();
            $table->char('currency_code', 3)->default('BDT');
            $table->bigInteger('minimum_order_amount')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
