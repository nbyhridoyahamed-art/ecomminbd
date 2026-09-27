<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bd_upazilas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bd_district_id')->constrained('bd_districts')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_bn');
            $table->string('code');
            $table->timestamps();

            $table->unique(['bd_district_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_upazilas');
    }
};
