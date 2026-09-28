<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            // Plain text, not HTML/Markdown — a real WYSIWYG editor (TipTap)
            // is deliberately reserved for Phase 14 (Blog); see
            // COMPONENT_INVENTORY.md. Rendered with white-space: pre-line on
            // the storefront, the same convention Product.description
            // already uses.
            $table->text('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            // draft/published, not Category's active/inactive — a page
            // being written shouldn't be live by default.
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
