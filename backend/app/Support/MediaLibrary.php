<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The one place a file actually lands on disk, so every upload path —
 * the reusable Media Library's own upload, the legacy folder-based
 * UploadController, and ProductImageController's gallery upload — ends up
 * as a real, reusable `media` row instead of three copies of the same
 * Str::uuid()-filename-plus-storeAs() logic drifting apart over time.
 */
class MediaLibrary
{
    public static function store(UploadedFile $file, int $storeId, ?int $uploadedBy, string $folder = 'media'): Media
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return Media::create([
            'store_id' => $storeId,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
