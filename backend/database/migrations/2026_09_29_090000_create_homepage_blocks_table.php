<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // hero|category_grid|... — see App\Support\HomepageBlockTypes for
            // the full registry (spec section 59). Immutable after creation
            // (delete and re-add for a different type), since a block's
            // settings shape is type-specific.
            $table->string('type');
            // Content: headings, images, product/category selection, etc —
            // shape is type-specific, validated by HomepageBlockTypes.
            $table->json('settings');
            // Non-responsive design tokens (background/text color, border
            // radius, box shadow) — deliberately small; layout-affecting
            // properties live in `responsive` below (spec section 60/61
            // treats these as two separate concerns).
            $table->json('styles')->nullable();
            // { desktop:{width,height,padding,margin,font_size,columns,gap,
            //   alignment,display}, tablet:{...}, mobile:{...} } — every key
            // optional per breakpoint; unset means "inherit the block
            // renderer's own default," never a silent zero.
            $table->json('responsive')->nullable();
            // { desktop: bool, tablet: bool, mobile: bool } — show/hide per
            // breakpoint, independent of is_active (which is the
            // draft/live gate, not a display toggle).
            $table->json('visibility')->nullable();
            // none|fade|slide|scale|reveal — spec section 122's animation
            // vocabulary, applied to the block's mount transition only.
            $table->string('animation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // Drafts by default (see HomepageBlockController::store()) —
            // only a dedicated publish action (builder.publish) can make a
            // block live, deliberately separate from builder.edit which
            // covers everything else.
            $table->boolean('is_active')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // No soft deletes: a block is pure presentation config, never
            // referenced by another table — its full history lives in
            // homepage_block_revisions instead.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_blocks');
    }
};
