<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedSection\SavedSectionRequest;
use App\Http\Resources\HomepageBlockResource;
use App\Http\Resources\SavedSectionResource;
use App\Models\HomepageBlock;
use App\Models\SavedSection;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedSection::class);

        $sections = SavedSection::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->orderBy('name')
            ->get();

        return ApiResponse::success(SavedSectionResource::collection($sections), 'Saved sections fetched successfully.');
    }

    public function store(SavedSectionRequest $request): JsonResponse
    {
        $this->authorize('create', SavedSection::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $section = SavedSection::create($data);

        return ApiResponse::success(new SavedSectionResource($section), 'Saved section created successfully.', status: 201);
    }

    public function destroy(SavedSection $savedSection): JsonResponse
    {
        $this->authorize('delete', $savedSection);

        $savedSection->delete();

        return ApiResponse::success(message: 'Saved section deleted successfully.');
    }

    /**
     * Inserts a copy of this saved section as a new draft block at the end
     * of the target store's homepage — the "reuse across pages" half of
     * spec section 63 (Wave 1 only has the homepage to insert onto).
     */
    public function insert(Request $request, SavedSection $savedSection): JsonResponse
    {
        $this->authorize('view', $savedSection);
        $this->authorize('create', HomepageBlock::class);

        $storeId = $request->integer('store_id', $savedSection->store_id);

        $block = HomepageBlock::create([
            'store_id' => $storeId,
            'type' => $savedSection->type,
            'settings' => $savedSection->settings,
            'styles' => $savedSection->styles,
            'responsive' => $savedSection->responsive,
            'visibility' => $savedSection->visibility,
            'animation' => $savedSection->animation,
            'is_active' => false,
            'sort_order' => (int) HomepageBlock::where('store_id', $storeId)->max('sort_order') + 1,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new HomepageBlockResource($block), 'Saved section inserted successfully.', status: 201);
    }
}
