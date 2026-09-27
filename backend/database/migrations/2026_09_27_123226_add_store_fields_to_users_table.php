<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->foreignId('current_store_id')->nullable()->after('phone')
                ->constrained('stores')->nullOnDelete();
            $table->string('locale', 5)->default('en')->after('current_store_id');
            $table->string('timezone')->default('Asia/Dhaka')->after('locale');
            $table->string('avatar_path')->nullable()->after('timezone');
            $table->string('status')->default('active')->after('avatar_path');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_store_id');
            $table->dropColumn(['uuid', 'phone', 'locale', 'timezone', 'avatar_path', 'status', 'deleted_at']);
        });
    }
};
