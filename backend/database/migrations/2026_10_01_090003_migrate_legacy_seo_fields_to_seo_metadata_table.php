<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three earlier phases each grew their own ad-hoc SEO columns —
     * products.seo_title/seo_description/focus_keyword (Phase 5),
     * pages.meta_title/meta_description (Phase 12),
     * blog_posts.meta_title/meta_description (Phase 14) — rather than
     * sharing one shape. Phase 15 supersedes all three with the polymorphic
     * seo_metadata table created just before this migration: existing
     * values are copied over first, then the legacy columns are dropped, the
     * same "supersede entirely" approach Phase 14 used for
     * blog_posts.is_active -> status.
     */
    public function up(): void
    {
        $this->backfill('products', 'App\\Models\\Product', 'seo_title', 'seo_description', 'focus_keyword');
        $this->backfill('pages', 'App\\Models\\Page', 'meta_title', 'meta_description');
        $this->backfill('blog_posts', 'App\\Models\\BlogPost', 'meta_title', 'meta_description');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'focus_keyword']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('focus_keyword')->nullable();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
        });

        $this->restore('products', 'App\\Models\\Product', 'seo_title', 'seo_description', 'focus_keyword');
        $this->restore('pages', 'App\\Models\\Page', 'meta_title', 'meta_description');
        $this->restore('blog_posts', 'App\\Models\\BlogPost', 'meta_title', 'meta_description');
    }

    private function backfill(string $table, string $entityType, string $titleColumn, string $descriptionColumn, ?string $focusKeywordColumn = null): void
    {
        $now = now();

        $query = DB::table($table)
            ->whereNotNull($titleColumn)
            ->orWhereNotNull($descriptionColumn);

        if ($focusKeywordColumn) {
            $query->orWhereNotNull($focusKeywordColumn);
        }

        foreach ($query->get() as $row) {
            DB::table('seo_metadata')->insert([
                'store_id' => $row->store_id,
                'entity_type' => $entityType,
                'entity_id' => $row->id,
                'title' => $row->{$titleColumn},
                'description' => $row->{$descriptionColumn},
                'focus_keyword' => $focusKeywordColumn ? $row->{$focusKeywordColumn} : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function restore(string $table, string $entityType, string $titleColumn, string $descriptionColumn, ?string $focusKeywordColumn = null): void
    {
        foreach (DB::table('seo_metadata')->where('entity_type', $entityType)->get() as $row) {
            $values = [
                $titleColumn => $row->title,
                $descriptionColumn => $row->description,
            ];

            if ($focusKeywordColumn) {
                $values[$focusKeywordColumn] = $row->focus_keyword;
            }

            DB::table($table)->where('id', $row->entity_id)->update($values);
        }

        DB::table('seo_metadata')->where('entity_type', $entityType)->delete();
    }
};
