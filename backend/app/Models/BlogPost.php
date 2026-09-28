<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Deliberately minimal — exists only to back the homepage builder's "Blog
 * Posts" block (spec section 59). NOT Phase 14 (Blog CMS): no categories,
 * tags, authors, or rich editor here. See DEVELOPMENT_ROADMAP.md's Phase 13
 * scope note.
 */
#[Fillable(['store_id', 'title', 'slug', 'excerpt', 'featured_image_url', 'published_at', 'is_active'])]
class BlogPost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BlogPost $post) {
            $post->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
