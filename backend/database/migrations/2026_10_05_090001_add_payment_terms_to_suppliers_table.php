<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Purely informational (due_on_receipt/net_15/net_30/net_60) — shown
            // on the supplier's ledger for staff judgment, not used to compute
            // an automatic due date or "overdue" flag. See SupplierRequest.
            $table->string('payment_terms')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('payment_terms');
        });
    }
};
