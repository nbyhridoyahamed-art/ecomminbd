<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SeoTemplate\SeoTemplateRequest;
use App\Http\Resources\SeoTemplateResource;
use App\Models\SeoTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SeoTemplate::class);

        $templates = SeoTemplate::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->orderBy('entity_type')
            ->get();

        return ApiResponse::success(SeoTemplateResource::collection($templates), 'SEO templates fetched successfully.');
    }

    public function store(SeoTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', SeoTemplate::class);

        $template = SeoTemplate::create($request->validated());

        return ApiResponse::success(new SeoTemplateResource($template), 'SEO template created successfully.', status: 201);
    }

    public function show(SeoTemplate $seoTemplate): JsonResponse
    {
        $this->authorize('view', $seoTemplate);

        return ApiResponse::success(new SeoTemplateResource($seoTemplate), 'SEO template fetched successfully.');
    }

    public function update(SeoTemplateRequest $request, SeoTemplate $seoTemplate): JsonResponse
    {
        $this->authorize('update', $seoTemplate);

        $seoTemplate->update($request->validated());

        return ApiResponse::success(new SeoTemplateResource($seoTemplate), 'SEO template updated successfully.');
    }

    public function destroy(SeoTemplate $seoTemplate): JsonResponse
    {
        $this->authorize('delete', $seoTemplate);

        $seoTemplate->delete();

        return ApiResponse::success(message: 'SEO template deleted successfully.');
    }
}
