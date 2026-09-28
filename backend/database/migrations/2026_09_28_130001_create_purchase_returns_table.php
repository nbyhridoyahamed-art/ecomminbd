<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Multiple returns per purchase order are allowed (e.g. separate
            // return requests against different lines, or found later after
            // an earlier return already went back) — not unique.
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('return_number');
            // requested -> approved -> shipped_back (drives the stock
            // decrement — see PurchaseReturnController::shipBack()) ->
            // credited, or rejected (terminal, from requested/approved).
            $table->string('status')->default('requested');
            $table->text('reason')->nullable();
            // Set only once status becomes credited — a suggested amount
            // (sum of the covered items' original unit cost) that staff can
            // override, same "computed default, editable" pattern as
            // ReturnController::refund()'s refund_amount. A supplier credit
            // note, not a cash refund — no accounts-payable ledger exists
            // yet to apply it against (see DATABASE_DESIGN.md section 2).
            $table->bigInteger('credit_amount')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'return_number']);
            $table->index(['store_id', 'status']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
