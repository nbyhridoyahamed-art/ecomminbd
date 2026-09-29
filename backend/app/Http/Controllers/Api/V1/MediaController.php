<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\MediaUpdateRequest;
use App\Http\Requests\Catalog\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Support\ApiResponse;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Media::class);
        $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id']]);

        $perPage = min((int) $request->integer('per_page', 24), 100);

        $media = Media::query()
            ->where('store_id', $request->integer('store_id'))
            ->with('uploader')
            ->when($request->filled('search'), fn ($query) => $query->where('filename', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            MediaResource::collection($media),
            'Media fetched successfully.',
            [
                'current_page' => $media->currentPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
                'last_page' => $media->lastPage(),
            ],
        );
    }

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $this->authorize('create', Media::class);

        $media = MediaLibrary::store($request->file('image'), $request->validated('store_id'), $request->user()->id);
        $media->update(['alt_text' => $request->validated('alt_text')]);

        return ApiResponse::success(new MediaResource($media), 'Media uploaded successfully.', status: 201);
    }

    public function update(MediaUpdateRequest $request, Media $medium): JsonResponse
    {
        $this->authorize('update', $medium);

        $medium->update(['alt_text' => $request->validated('alt_text')]);

        return ApiResponse::success(new MediaResource($medium), 'Media updated successfully.');
    }

    public function destroy(Media $medium): JsonResponse
    {
        $this->authorize('delete', $medium);

        Storage::disk($medium->disk)->delete($medium->path);
        $medium->delete();

        return ApiResponse::success(message: 'Media deleted successfully.');
    }
}
