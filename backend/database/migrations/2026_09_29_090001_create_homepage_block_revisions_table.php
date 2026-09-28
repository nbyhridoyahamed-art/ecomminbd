<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_block_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homepage_block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // A full snapshot of the block's own columns (type, settings,
            // styles, responsive, visibility, animation, is_active) at the
            // moment this revision was captured — restoring means copying
            // this JSON back onto the live row, never replaying a diff.
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_block_revisions');
    }
};
