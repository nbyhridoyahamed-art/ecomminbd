<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentSessionRequest;
use App\Http\Resources\StockAdjustmentSessionResource;
use App\Models\Product;
use App\Models\StockAdjustmentSession;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use App\Support\StockAdjuster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A stocktake session groups the many per-product adjustments a physical
 * count produces under one reference, rather than each becoming its own
 * disconnected stock_movements row — see StockAdjustmentController for the
 * quick single-item action this deliberately stays separate from.
 */
class StockAdjustmentSessionController extends Controller
{
    private const RELATIONS = [
        'warehouse', 'creator',
        'movements.product', 'movements.productVariant.attributeValues.attribute', 'movements.warehouse', 'movements.creator',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $sessions = StockAdjustmentSession::query()
            ->with(self::RELATIONS)
            ->where('store_id', $request->integer('store_id'))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            StockAdjustmentSessionResource::collection($sessions),
            'Stocktake sessions fetched successfully.',
            [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ],
        );
    }

    public function show(StockAdjustmentSession $stockAdjustmentSession): JsonResponse
    {
        if (! request()->user()->can('inventory.view')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success(
            new StockAdjustmentSessionResource($stockAdjustmentSession->load(self::RELATIONS)),
            'Stocktake session fetched successfully.',
        );
    }

    public function store(StockAdjustmentSessionRequest $request): JsonResponse
    {
        if (! $request->user()->can('inventory.adjust')) {
            throw new AuthorizationException;
        }

        $data = $request->validated();
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        if ($warehouse->store_id !== $data['store_id']) {
            return ApiResponse::error('The warehouse must belong to the selected store.', [], 422);
        }

        $productIds = array_column($data['items'], 'product_id');
        $products = Product::query()->whereIn('id', $productIds)->where('store_id', $data['store_id'])->get()->keyBy('id');
        if ($products->count() !== count(array_unique($productIds))) {
            return ApiResponse::error('One or more products do not belong to the selected store.', [], 422);
        }

        $reference = $data['reference'] ?? 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        if (StockAdjustmentSession::where('store_id', $data['store_id'])->where('reference', $reference)->exists()) {
            return ApiResponse::error('A stocktake session with that reference already exists.', [], 422);
        }

        try {
            $session = DB::transaction(function () use ($data, $warehouse, $products, $reference, $request) {
                $session = StockAdjustmentSession::create([
                    'store_id' => $data['store_id'],
                    'warehouse_id' => $warehouse->id,
                    'reference' => $reference,
                    'note' => $data['note'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $item) {
                    StockAdjuster::apply(
                        product: $products[$item['product_id']],
                        productVariantId: $item['product_variant_id'] ?? null,
                        warehouse: $warehouse,
                        direction: $item['direction'],
                        quantity: $item['quantity'],
                        reason: $item['reason'] ?? null,
                        userId: $request->user()->id,
                        referenceType: StockAdjustmentSession::class,
                        referenceId: $session->id,
                    );
                }

                return $session;
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new StockAdjustmentSessionResource($session->load(self::RELATIONS)),
            'Stocktake session recorded successfully.',
            status: 201,
        );
    }
}
