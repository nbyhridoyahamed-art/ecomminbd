<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    private const RELATIONS = ['fromWarehouse', 'toWarehouse', 'items.product', 'items.productVariant.attributeValues.attribute', 'creator'];

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

        try {
            $transfer = DB::transaction(function () use ($data, $fromWarehouse, $toWarehouse, $request) {
                $transfer = StockTransfer::create([
                    'store_id' => $data['store_id'],
                    'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'from_warehouse_id' => $fromWarehouse->id,
                    'to_warehouse_id' => $toWarehouse->id,
                    'note' => $data['note'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $item) {
                    $this->moveStock($transfer, $item['product_id'], $item['product_variant_id'] ?? null, $item['quantity'], $fromWarehouse, $toWarehouse, $request->user()->id);

                    StockTransferItem::create([
                        'stock_transfer_id' => $transfer->id,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity' => $item['quantity'],
                    ]);
                }

                return $transfer;
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new StockTransferResource($transfer->load(self::RELATIONS)),
            'Stock transfer completed successfully.',
            status: 201,
        );
    }

    private function moveStock(
        StockTransfer $transfer,
        int $productId,
        ?int $productVariantId,
        int $quantity,
        Warehouse $from,
        Warehouse $to,
        int $userId,
    ): void {
        $sourceLevel = StockLevel::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $from->id)
            ->lockForUpdate()
            ->first();

        $sourceBefore = $sourceLevel?->quantity ?? 0;
        $sourceAfter = $sourceBefore - $quantity;

        if ($sourceAfter < 0) {
            $product = Product::findOrFail($productId);
            throw new InsufficientStockException("Not enough stock of \"{$product->name}\" at {$from->name} to transfer {$quantity} unit(s).");
        }

        // Stock already reserved for pending/processing orders can't be transferred out,
        // or ship() would later try to decrement on-hand quantity below zero.
        if ($sourceAfter < ($sourceLevel?->quantity_reserved ?? 0)) {
            $product = Product::findOrFail($productId);
            throw new InsufficientStockException("Cannot transfer \"{$product->name}\" out of {$from->name}: that stock is reserved for pending orders.");
        }

        $sourceLevel
            ? $sourceLevel->update(['quantity' => $sourceAfter])
            : StockLevel::create([
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $from->id,
                'quantity' => $sourceAfter,
            ]);

        StockMovement::create([
            'store_id' => $transfer->store_id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $from->id,
            'type' => 'transfer_out',
            'quantity' => $quantity,
            'quantity_before' => $sourceBefore,
            'quantity_after' => $sourceAfter,
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
            'created_by' => $userId,
        ]);

        $destLevel = StockLevel::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $to->id)
            ->lockForUpdate()
            ->first();

        $destBefore = $destLevel?->quantity ?? 0;
        $destAfter = $destBefore + $quantity;

        $destLevel
            ? $destLevel->update(['quantity' => $destAfter])
            : StockLevel::create([
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $to->id,
                'quantity' => $destAfter,
            ]);

        StockMovement::create([
            'store_id' => $transfer->store_id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $to->id,
            'type' => 'transfer_in',
            'quantity' => $quantity,
            'quantity_before' => $destBefore,
            'quantity_after' => $destAfter,
            'reference_type' => StockTransfer::class,
            'reference_id' => $transfer->id,
            'created_by' => $userId,
        ]);
    }
}
