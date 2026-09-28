<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['store_id', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'status', 'created_by'])]
class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Page $page) {
            $page->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
