<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UploadProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $file = $request->file('image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('products', $filename, 'public');

        $image = DB::transaction(function () use ($product, $path, $request) {
            $isFirst = ! $product->images()->exists();

            return $product->images()->create([
                'path' => $path,
                'alt_text' => $request->validated('alt_text'),
                'sort_order' => $product->images()->count(),
                'is_primary' => $isFirst,
            ]);
        });

        return ApiResponse::success(new ProductImageResource($image), 'Image uploaded successfully.', status: 201);
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->authorize('update', $product);

        if ($image->product_id !== $product->id) {
            return ApiResponse::error('This image does not belong to the given product.', [], 404);
        }

        DB::transaction(function () use ($product, $image) {
            $wasPrimary = $image->is_primary;
            Storage::disk('public')->delete($image->path);
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
