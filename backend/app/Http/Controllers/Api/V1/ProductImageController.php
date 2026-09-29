<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\AttachProductImageRequest;
use App\Http\Requests\Product\UploadProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ApiResponse;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $media = MediaLibrary::store($request->file('image'), $product->store_id, $request->user()->id, 'products');

        $image = $this->createImage($product, $media->path, $request->validated('alt_text'));

        return ApiResponse::success(new ProductImageResource($image), 'Image uploaded successfully.', status: 201);
    }

    public function attach(AttachProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $media = Media::query()->where('store_id', $product->store_id)->findOrFail($request->validated('media_id'));

        $image = $this->createImage($product, $media->path, $request->validated('alt_text') ?? $media->alt_text);

        return ApiResponse::success(new ProductImageResource($image), 'Image added from the media library.', status: 201);
    }

    private function createImage(Product $product, string $path, ?string $altText): ProductImage
    {
        return DB::transaction(function () use ($product, $path, $altText) {
            $isFirst = ! $product->images()->exists();

            return $product->images()->create([
                'path' => $path,
                'alt_text' => $altText,
                'sort_order' => $product->images()->count(),
                'is_primary' => $isFirst,
            ]);
        });
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);

        if ($image->product_id !== $product->id) {
            return ApiResponse::error('This image does not belong to the given product.', [], 404);
        }

        DB::transaction(function () use ($product, $image) {
            $wasPrimary = $image->is_primary;
            // Only unlinks this product from the file — never deletes it
            // from disk. Since the Media Library lets the same file be
            // picked for more than one entity (see MediaLibrary::store()),
            // only the library's own explicit delete (MediaController)
            // removes the underlying file; every consumer removing a row
            // that merely points at it would otherwise break every other
            // reference to the same path.
            $image->delete();

            if ($wasPrimary) {
                $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
        });

        return ApiResponse::success(message: 'Image deleted successfully.');
    }

    public function markPrimary(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);

        if ($image->product_id !== $product->id) {
            return ApiResponse::error('This image does not belong to the given product.', [], 404);
        }

        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return ApiResponse::success(new ProductImageResource($image->fresh()), 'Primary image updated.');
    }
}
