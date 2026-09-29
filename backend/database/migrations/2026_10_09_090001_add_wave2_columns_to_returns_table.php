<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            // Only meaningful once status is refunded — original_payment
            // (default, Wave 1's only behavior) or store_credit, which also
            // writes a customer_store_credits ledger entry (see
            // ReturnController::refund()).
            $table->string('refund_method')->default('original_payment')->after('refund_amount');
            // Set once receive() processes at least one exchange item — the
            // zero-value Order it created to ship the replacement product(s).
            $table->foreignId('replacement_order_id')->nullable()->after('note')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_order_id');
            $table->dropColumn('refund_method');
        });
    }
};
