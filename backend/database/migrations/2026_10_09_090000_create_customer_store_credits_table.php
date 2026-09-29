<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_store_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // Append-only ledger, same convention as stock_movements — a
            // customer's balance is never stored, always sum(amount). Positive
            // = credit issued (e.g. a return refunded as store credit),
            // negative = credit redeemed (spent on an order) or reversed
            // (a redemption undone by an order edit/cancel).
            $table->bigInteger('amount');
            // ::class string of the OrderReturn (issuance) or Order
            // (redemption/reversal) this entry originated from — internal
            // bookkeeping only; note carries the human-readable line.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'customer_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_store_credits');
    }
};
