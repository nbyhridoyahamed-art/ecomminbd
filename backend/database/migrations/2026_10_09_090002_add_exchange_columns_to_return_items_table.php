<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            // Set at request time (the customer/staff already knows what they
            // want instead) — orthogonal to restock: a returned item can be
            // both restocked and exchanged for something else. Acted on at
            // receive() time, same as restock.
            $table->foreignId('exchange_product_id')->nullable()->after('restock')->constrained('products')->nullOnDelete();
            $table->foreignId('exchange_product_variant_id')->nullable()->after('exchange_product_id')->constrained('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_product_variant_id');
            $table->dropConstrainedForeignId('exchange_product_id');
        });
    }
};
