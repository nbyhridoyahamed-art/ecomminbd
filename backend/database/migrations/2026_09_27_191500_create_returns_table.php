<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            // Multiple returns per order are allowed (e.g. two separate return
            // requests on different items) — unlike shipments.order_id, this is
            // not unique.
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('return_number');
            // requested -> approved -> received (drives restock) -> refunded
            // (may flip orders.payment_status — see ReturnController::refund()),
            // or rejected (terminal, from requested/approved).
            $table->string('status')->default('requested');
            $table->text('reason')->nullable();
            // Set only once status becomes refunded — a suggested amount
            // (sum of the covered items' order-time unit price) that staff can
            // override, same "computed default, editable" pattern as
            // ShipmentController::delivered()'s cod_amount_collected.
            $table->bigInteger('refund_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'return_number']);
            $table->index(['store_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
