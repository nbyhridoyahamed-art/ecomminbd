<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows an order to have more than one shipment over its lifetime — a
 * re-dispatch after a failed delivery/returned_to_seller shipment needs a
 * new shipment row, and Wave 1's unique index made that impossible (see
 * DEVELOPMENT_ROADMAP.md's Phase 9 scope note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->unique('order_id');
        });
    }
};
