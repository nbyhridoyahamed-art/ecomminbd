<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A store-wide default title/description pattern per entity type (e.g.
     * "{{title}} | {{store_name}}" for products), used to pre-fill an
     * entity's seo_metadata when it has no override of its own yet.
     */
    public function up(): void
    {
        Schema::create('seo_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('title_template')->nullable();
            $table->string('description_template', 500)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_templates');
    }
};
