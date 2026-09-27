<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upload\UploadImageRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(UploadImageRequest $request): JsonResponse
    {
        $folder = $request->validated('folder');

        if (! $request->user()->can("{$folder}.create") && ! $request->user()->can("{$folder}.update")) {
            throw new AuthorizationException;
        }

        $file = $request->file('image');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return ApiResponse::success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Image uploaded successfully.', status: 201);
    }
}
