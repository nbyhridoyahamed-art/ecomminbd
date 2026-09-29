<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use App\Support\StockAdjuster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    private const RELATIONS = [
        'fromWarehouse', 'toWarehouse', 'items.product', 'items.productVariant.attributeValues.attribute',
        'creator', 'statusHistory.creator', 'movements.warehouse',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        $request->validate(['store_id' => ['required', 'exists:stores,id']]);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $transfers = StockTransfer::query()
            ->with(self::RELATIONS)
            ->where('store_id', $request->integer('store_id'))
            ->when($request->filled('warehouse_id'), function ($query) use ($request) {
                $warehouseId = $request->integer('warehouse_id');
                $query->where(function ($q) use ($warehouseId) {
                    $q->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            StockTransferResource::collection($transfers),
            'Stock transfers fetched successfully.',
            [
                'current_page' => $transfers->currentPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
                'last_page' => $transfers->lastPage(),
            ],
        );
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        if (! request()->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        return ApiResponse::success(
            new StockTransferResource($stockTransfer->load(self::RELATIONS)),
            'Stock transfer fetched successfully.',
        );
    }

    public function store(StockTransferRequest $request): JsonResponse
    {
        if (! $request->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        $data = $request->validated();
        $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
        $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);

        if ($fromWarehouse->store_id !== $data['store_id'] || $toWarehouse->store_id !== $data['store_id']) {
            return ApiResponse::error('Both warehouses must belong to the selected store.', [], 422);
        }

        $productIds = array_column($data['items'], 'product_id');
        $productCount = Product::query()->whereIn('id', $productIds)->where('store_id', $data['store_id'])->count();
        if ($productCount !== count(array_unique($productIds))) {
            return ApiResponse::error('One or more products do not belong to the selected store.', [], 422);
        }

        // Deliberately no stock impact yet — a transfer is just a paper
        // record until ship() actually moves anything, the same
        // draft-holds-nothing precedent purchase_orders already set (only
        // recording a receipt touches stock there). Unlike an Order, a
        // pending transfer also doesn't reserve source stock: it's two of
        // the same store's own warehouses, created by staff, not a
        // storefront cart racing other customers, so that extra
        // bookkeeping isn't worth it — ship() re-checks availability for
        // real at the moment it matters.
        $transfer = DB::transaction(function () use ($data, $fromWarehouse, $toWarehouse, $request) {
            $transfer = StockTransfer::create([
                'store_id' => $data['store_id'],
                'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'status' => 'pending',
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            $transfer->statusHistory()->create([
                'from_status' => null,
                'to_status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            return $transfer;
        });

        return ApiResponse::success(
            new StockTransferResource($transfer->load(self::RELATIONS)),
            'Stock transfer created successfully.',
            status: 201,
        );
    }

    public function ship(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if (! $request->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        if ($stockTransfer->status !== 'pending') {
            return ApiResponse::error('Only a pending transfer can be shipped.', [], 422);
        }

        $fromWarehouse = $stockTransfer->fromWarehouse;

        try {
            DB::transaction(function () use ($stockTransfer, $fromWarehouse, $request) {
                foreach ($stockTransfer->items()->with('product')->get() as $item) {
                    StockAdjuster::apply(
                        product: $item->product,
                        productVariantId: $item->product_variant_id,
                        warehouse: $fromWarehouse,
                        direction: 'decrease',
                        quantity: $item->quantity,
                        reason: null,
                        userId: $request->user()->id,
                        referenceType: StockTransfer::class,
                        referenceId: $stockTransfer->id,
                        movementType: 'transfer_out',
                    );
                }

                $this->transition($stockTransfer, 'in_transit', $request->user()->id);
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new StockTransferResource($stockTransfer->load(self::RELATIONS)),
            'Stock transfer marked in transit.',
        );
    }

    public function receive(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if (! $request->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        if ($stockTransfer->status !== 'in_transit') {
            return ApiResponse::error('Only an in-transit transfer can be received.', [], 422);
        }

        $toWarehouse = $stockTransfer->toWarehouse;

        // No InsufficientStockException catch needed here — an increase can
        // never take on-hand quantity below zero or below what's reserved,
        // so StockAdjuster::apply() can't throw on this path.
        DB::transaction(function () use ($stockTransfer, $toWarehouse, $request) {
            foreach ($stockTransfer->items()->with('product')->get() as $item) {
                StockAdjuster::apply(
                    product: $item->product,
                    productVariantId: $item->product_variant_id,
                    warehouse: $toWarehouse,
                    direction: 'increase',
                    quantity: $item->quantity,
                    reason: null,
                    userId: $request->user()->id,
                    referenceType: StockTransfer::class,
                    referenceId: $stockTransfer->id,
                    movementType: 'transfer_in',
                );
            }

            $this->transition($stockTransfer, 'received', $request->user()->id);
        });

        return ApiResponse::success(
            new StockTransferResource($stockTransfer->load(self::RELATIONS)),
            'Stock transfer received.',
        );
    }

    public function cancel(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if (! $request->user()->can('inventory.transfer')) {
            throw new AuthorizationException;
        }

        if ($stockTransfer->status !== 'pending') {
            return ApiResponse::error('Only a pending transfer can be cancelled — one already shipped must be received, not cancelled.', [], 422);
        }

        $this->transition($stockTransfer, 'cancelled', $request->user()->id, $request->input('note'));

        return ApiResponse::success(
            new StockTransferResource($stockTransfer->load(self::RELATIONS)),
            'Stock transfer cancelled.',
        );
    }

    private function transition(StockTransfer $transfer, string $toStatus, int $userId, ?string $note = null): void
    {
        $fromStatus = $transfer->status;
        $transfer->update(['status' => $toStatus]);

        $transfer->statusHistory()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }
}
