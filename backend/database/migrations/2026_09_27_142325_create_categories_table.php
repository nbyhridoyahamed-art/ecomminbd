<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
