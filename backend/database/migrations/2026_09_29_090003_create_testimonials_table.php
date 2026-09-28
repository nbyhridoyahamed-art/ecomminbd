<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backs both the "Testimonials" and "Reviews" homepage blocks (spec
        // section 59) — the two are the same underlying content (a quoted
        // customer endorsement, optionally rated) rendered with a different
        // card emphasis, not two separate data sources. This is
        // deliberately NOT the product review system Catalog Wave 2 still
        // defers (DATABASE_DESIGN.md section 2) — that is a per-product,
        // verified-purchase review tied to an order; this is homepage
        // marketing copy a staff member curates directly.
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->string('avatar_url')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
