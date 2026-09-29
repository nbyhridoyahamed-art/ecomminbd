<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\UploadImageRequest;
use App\Support\ApiResponse;
use App\Support\MediaLibrary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(UploadImageRequest $request): JsonResponse
    {
        $folder = $request->validated('folder');

        if (! $request->user()->can("{$folder}.create") && ! $request->user()->can("{$folder}.update")) {
            throw new AuthorizationException;
        }

        $media = MediaLibrary::store($request->file('image'), $request->validated('store_id'), $request->user()->id, $folder);

        return ApiResponse::success([
            'path' => $media->path,
            'url' => $media->url(),
        ], 'Image uploaded successfully.', status: 201);
    }
}
