<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One polymorphic table backs SEO metadata for every entity type
     * (products, categories, brands, pages, blog posts/categories/tags, and
     * the store itself for site-wide/homepage SEO) rather than duplicating
     * title/description columns per table — see DATABASE_DESIGN.md's
     * "Target Schema for Future Phases" note on entity_type/entity_id over
     * one nullable FK per entity type (the same convention activity_logs
     * already uses).
     */
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('title')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_title')->nullable();
            $table->string('twitter_description', 500)->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots')->nullable();
            $table->json('schema_json')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
