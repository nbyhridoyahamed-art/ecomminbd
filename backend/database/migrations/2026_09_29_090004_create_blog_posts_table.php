<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately minimal, built only to give the homepage builder's
        // "Blog Posts" block (spec section 59) real data to source from.
        // This is NOT Phase 14 (Blog CMS) — no categories, tags, authors,
        // rich editor, revisions, or scheduling here; see
        // DEVELOPMENT_ROADMAP.md's Phase 13 scope note. Phase 14 is
        // expected to absorb or replace this table with the real one.
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
