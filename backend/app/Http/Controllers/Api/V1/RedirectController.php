<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Redirect\RedirectRequest;
use App\Http\Resources\RedirectResource;
use App\Models\Redirect;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Redirect::class);

        $redirects = Redirect::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('from_path', 'like', $term)->orWhere('to_path', 'like', $term);
            }))
            ->orderBy('from_path')
            ->get();

        return ApiResponse::success(RedirectResource::collection($redirects), 'Redirects fetched successfully.');
    }

    public function store(RedirectRequest $request): JsonResponse
    {
        $this->authorize('create', Redirect::class);

        $data = $request->validated();
        $data['status_code'] ??= 301;
        $data['hits_count'] = 0;

        $redirect = Redirect::create($data);

        return ApiResponse::success(new RedirectResource($redirect), 'Redirect created successfully.', status: 201);
    }

    public function show(Redirect $redirect): JsonResponse
    {
        $this->authorize('view', $redirect);

        return ApiResponse::success(new RedirectResource($redirect), 'Redirect fetched successfully.');
    }

    public function update(RedirectRequest $request, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $redirect);

        $redirect->update($request->validated());

        return ApiResponse::success(new RedirectResource($redirect), 'Redirect updated successfully.');
    }

    public function destroy(Redirect $redirect): JsonResponse
    {
        $this->authorize('delete', $redirect);

        $redirect->delete();

        return ApiResponse::success(message: 'Redirect deleted successfully.');
    }
}
