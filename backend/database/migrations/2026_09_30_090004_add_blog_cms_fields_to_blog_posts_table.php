<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 14 takes over the minimal `blog_posts` table Phase 13 built only
     * to back the homepage builder's "Blog Posts" block (title/slug/excerpt/
     * featured_image_url/published_at/is_active) and turns it into the real
     * blog CMS resource: rich body content, a category, an author, SEO
     * fields, and a real draft/published status. `is_active` is dropped —
     * `status` supersedes it, and the homepage builder's own resolver is
     * updated to match (see ResolvesHomepageBlocks).
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->longText('body')->nullable()->after('excerpt');
            $table->foreignId('blog_category_id')->nullable()->after('body')->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('blog_category_id')->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable()->after('created_by');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->string('status')->default('draft')->after('meta_description');
            $table->softDeletes();
        });

        // Preserve the previously-live posts' visibility: anything that was
        // is_active=true becomes status=published before the column is dropped,
        // rather than silently reverting every existing post to draft.
        DB::table('blog_posts')->where('is_active', true)->update(['status' => 'published']);

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        DB::table('blog_posts')->where('status', 'published')->update(['is_active' => true]);

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['blog_category_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['body', 'blog_category_id', 'created_by', 'meta_title', 'meta_description', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
