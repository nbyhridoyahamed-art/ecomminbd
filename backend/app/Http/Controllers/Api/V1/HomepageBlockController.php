<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomepageBlock\HomepageBlockReorderRequest;
use App\Http\Requests\HomepageBlock\HomepageBlockRequest;
use App\Http\Requests\HomepageBlock\HomepageBlockScheduleRequest;
use App\Http\Resources\HomepageBlockResource;
use App\Http\Resources\HomepageBlockRevisionResource;
use App\Http\Resources\SavedSectionResource;
use App\Models\HomepageBlock;
use App\Models\SavedSection;
use App\Support\ApiResponse;
use App\Support\HomepageBlockTypes;
use App\Support\ResolvesHomepageBlocks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomepageBlockController extends Controller
{
    use ResolvesHomepageBlocks;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomepageBlock::class);

        $blocks = HomepageBlock::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(HomepageBlockResource::collection($blocks), 'Homepage blocks fetched successfully.');
    }

    /**
     * Every block for the store — draft included — each resolved through
     * the exact same per-type logic the public storefront endpoint uses,
     * so the builder's canvas renders precisely what will go live the
     * moment a block is published, not an approximation of it.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomepageBlock::class);

        $storeId = $request->integer('store_id');

        $blocks = HomepageBlock::query()
            ->where('store_id', $storeId)
            ->orderBy('sort_order')
            ->get();

        $resolved = $blocks->map(fn (HomepageBlock $block) => [
            'id' => $block->id,
            'type' => $block->type,
            'settings' => $block->settings,
            'styles' => $block->styles ?? (object) [],
            'responsive' => $block->responsive ?? (object) [],
            'visibility' => $block->visibility ?? (object) [],
            'animation' => $block->animation,
            'is_active' => $block->is_active,
            'data' => $this->resolveBlockData($block, $storeId),
        ]);

        return ApiResponse::success($resolved, 'Homepage block preview fetched successfully.');
    }

    public function store(HomepageBlockRequest $request): JsonResponse
    {
        $this->authorize('create', HomepageBlock::class);

        $data = $request->validated();
        $data['store_id'] = $request->integer('store_id');
        $data['created_by'] = $request->user()->id;
        $data['settings'] = HomepageBlockTypes::normalizeSettingsForStorage($data['type'], $data['settings']);
        // Every new block is a draft — only the dedicated publish action
        // (builder.publish) can make it live, regardless of what the
        // caller sent (there is no is_active field to accept here at all).
        $data['is_active'] = false;
        $data['sort_order'] = (int) HomepageBlock::where('store_id', $data['store_id'])->max('sort_order') + 1;

        $block = HomepageBlock::create($data);

        return ApiResponse::success(new HomepageBlockResource($block), 'Homepage block created successfully.', status: 201);
    }

    public function show(HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('view', $homepageBlock);

        return ApiResponse::success(new HomepageBlockResource($homepageBlock->load('creator')), 'Homepage block fetched successfully.');
    }

    public function update(HomepageBlockRequest $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('update', $homepageBlock);

        $this->snapshot($homepageBlock, $request->user()->id);

        $data = $request->safe()->only(['settings', 'styles', 'responsive', 'visibility', 'animation']);
        $data['settings'] = HomepageBlockTypes::normalizeSettingsForStorage($homepageBlock->type, $data['settings']);

        $homepageBlock->update($data);

        return ApiResponse::success(new HomepageBlockResource($homepageBlock), 'Homepage block updated successfully.');
    }

    public function destroy(HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('delete', $homepageBlock);

        $homepageBlock->delete();

        return ApiResponse::success(message: 'Homepage block deleted successfully.');
    }

    public function reorder(HomepageBlockReorderRequest $request): JsonResponse
    {
        // Not tied to one Eloquent instance (it re-sorts a whole store's
        // blocks in one call), so this checks the permission directly
        // rather than through a policy method — same as
        // HomepageBlockPolicy::update()'s own builder.edit check.
        if (! $request->user()->can('builder.edit')) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($request) {
            foreach ($request->validated('order') as $index => $id) {
                HomepageBlock::where('id', $id)->update(['sort_order' => $index]);
            }
        });

        return ApiResponse::success(message: 'Homepage blocks reordered successfully.');
    }

    public function duplicate(Request $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('create', HomepageBlock::class);

        $duplicate = DB::transaction(function () use ($request, $homepageBlock) {
            HomepageBlock::where('store_id', $homepageBlock->store_id)
                ->where('sort_order', '>', $homepageBlock->sort_order)
                ->increment('sort_order');

            return HomepageBlock::create([
                ...$homepageBlock->toSnapshot(),
                'store_id' => $homepageBlock->store_id,
                'is_active' => false,
                'sort_order' => $homepageBlock->sort_order + 1,
                'created_by' => $request->user()->id,
            ]);
        });

        return ApiResponse::success(new HomepageBlockResource($duplicate), 'Homepage block duplicated successfully.', status: 201);
    }

    public function publish(Request $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('publish', $homepageBlock);

        $this->snapshot($homepageBlock, $request->user()->id);

        $homepageBlock->update(['is_active' => true, 'scheduled_at' => null]);

        return ApiResponse::success(new HomepageBlockResource($homepageBlock), 'Homepage block published successfully.');
    }

    public function unpublish(Request $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('publish', $homepageBlock);

        $this->snapshot($homepageBlock, $request->user()->id);

        $homepageBlock->update(['is_active' => false]);

        return ApiResponse::success(new HomepageBlockResource($homepageBlock), 'Homepage block unpublished successfully.');
    }

    public function schedule(HomepageBlockScheduleRequest $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('publish', $homepageBlock);

        $homepageBlock->update(['scheduled_at' => $request->validated('scheduled_at')]);

        return ApiResponse::success(new HomepageBlockResource($homepageBlock), 'Homepage block scheduled successfully.');
    }

    public function revisions(HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('view', $homepageBlock);

        $revisions = $homepageBlock->revisions()->with('creator')->limit(50)->get();

        return ApiResponse::success(HomepageBlockRevisionResource::collection($revisions), 'Homepage block revisions fetched successfully.');
    }

    public function restore(Request $request, HomepageBlock $homepageBlock, int $revision): JsonResponse
    {
        $this->authorize('update', $homepageBlock);

        $target = $homepageBlock->revisions()->findOrFail($revision);

        // Restoring is itself a change worth being able to undo.
        $this->snapshot($homepageBlock, $request->user()->id);

        $homepageBlock->update(collect($target->snapshot)->only([
            'settings', 'styles', 'responsive', 'visibility', 'animation',
        ])->toArray());

        return ApiResponse::success(new HomepageBlockResource($homepageBlock), 'Homepage block restored successfully.');
    }

    /** Saves this block's current configuration into the reusable saved-sections library (spec section 63). */
    public function saveAsSection(Request $request, HomepageBlock $homepageBlock): JsonResponse
    {
        $this->authorize('view', $homepageBlock);
        $this->authorize('create', SavedSection::class);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $section = SavedSection::create([
            ...collect($homepageBlock->toSnapshot())->except('is_active')->toArray(),
            'store_id' => $homepageBlock->store_id,
            'name' => $request->string('name'),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new SavedSectionResource($section), 'Saved section created successfully.', status: 201);
    }

    private function snapshot(HomepageBlock $block, int $userId): void
    {
        $block->revisions()->create([
            'store_id' => $block->store_id,
            'snapshot' => $block->toSnapshot(),
            'created_by' => $userId,
        ]);
    }
}
