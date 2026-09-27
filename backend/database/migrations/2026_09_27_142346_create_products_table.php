<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->string('barcode')->nullable();
            // Only 'simple' is fully functional today (Wave 1). The others are
            // reserved so the schema doesn't need a breaking change when
            // variable/digital/service/bundle/combo products ship (spec
            // section 13) — see DEVELOPMENT_ROADMAP.md Phase 5 Wave 2.
            $table->string('type')->default('simple');

            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            // Money stored as integer minor units (paisa) — never floats.
            $table->char('currency_code', 3)->default('BDT');
            $table->bigInteger('price_amount');
            $table->bigInteger('sale_price_amount')->nullable();
            $table->bigInteger('cost_price_amount')->nullable();
            $table->bigInteger('compare_at_price_amount')->nullable();

            $table->decimal('weight', 8, 2)->nullable();
            $table->string('weight_unit', 10)->nullable();

            $table->boolean('track_stock')->default(true);
            $table->unsignedInteger('low_stock_threshold')->nullable();

            $table->string('status')->default('draft');
            $table->boolean('featured')->default(false);

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('focus_keyword')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->unique(['store_id', 'sku']);
            $table->index(['store_id', 'status']);
            $table->index(['category_id']);
            $table->index(['brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
