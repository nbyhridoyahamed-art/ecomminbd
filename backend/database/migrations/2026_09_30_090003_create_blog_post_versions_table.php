<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The durable, server-side "already-saved change" history — same
        // role as Phase 13's homepage_block_revisions, one row per update
        // (a post is edited via a normal Save button, not a live-autosave
        // canvas, so this granularity is already the right one; no separate
        // local undo/redo layer is needed the way the homepage builder has).
        Schema::create('blog_post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_versions');
    }
};
