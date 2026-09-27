<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bd_division_id')->constrained('bd_divisions')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn');
            $table->string('code');
            $table->timestamps();

            $table->unique(['bd_division_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_districts');
    }
};
