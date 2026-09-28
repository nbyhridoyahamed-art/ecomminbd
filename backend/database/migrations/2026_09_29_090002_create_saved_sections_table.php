<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // A frozen copy of one block's configuration (spec section 63)
            // — same shape as homepage_blocks' own type/settings/styles/
            // responsive/visibility/animation, minus sort_order/is_active,
            // which only mean something once inserted onto a real page.
            $table->string('type');
            $table->json('settings');
            $table->json('styles')->nullable();
            $table->json('responsive')->nullable();
            $table->json('visibility')->nullable();
            $table->string('animation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_sections');
    }
};
