<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseReturnCreditRequest;
use App\Http\Requests\Purchasing\PurchaseReturnRequest;
use App\Http\Resources\PurchaseReturnResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Support\InsufficientStockException;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReturnController extends Controller
{
    private const RELATIONS = [
        'purchaseOrder.supplier', 'items.purchaseOrderItem.product', 'items.purchaseOrderItem.productVariant',
        'creator', 'statusHistory.creator',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $returns = PurchaseReturn::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('purchase_order_id'), fn ($query) => $query->where('purchase_order_id', $request->integer('purchase_order_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            PurchaseReturnResource::collection($returns),
            'Purchase returns fetched successfully.',
            [
                'current_page' => $returns->currentPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
                'last_page' => $returns->lastPage(),
            ],
        );
    }

    public function store(PurchaseReturnRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('create', PurchaseReturn::class);

        if (! in_array($purchaseOrder->status, ['partially_received', 'received'], true)) {
            return ApiResponse::error('Only a purchase order with something received can have a return requested.', [], 422);
        }

        $data = $request->validated();

        $return = DB::transaction(function () use ($data, $purchaseOrder, $request) {
            $return = PurchaseReturn::create([
                'store_id' => $purchaseOrder->store_id,
                'purchase_order_id' => $purchaseOrder->id,
                'return_number' => 'PRET-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'requested',
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                $return->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            $return->statusHistory()->create([
                'from_status' => null,
                'to_status' => 'requested',
                'created_by' => $request->user()->id,
            ]);

            return $return;
        });

        return ApiResponse::success(new PurchaseReturnResource($return->load(self::RELATIONS)), 'Purchase return requested successfully.', status: 201);
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('view', $purchaseReturn);

        return ApiResponse::success(new PurchaseReturnResource($purchaseReturn->load(self::RELATIONS)), 'Purchase return fetched successfully.');
    }

    public function approve(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('update', $purchaseReturn);

        if ($purchaseReturn->status !== 'requested') {
            return ApiResponse::error('Only a requested return can be approved.', [], 422);
        }

        $this->transition($purchaseReturn, 'approved');

        return ApiResponse::success(new PurchaseReturnResource($purchaseReturn->load(self::RELATIONS)), 'Purchase return approved.');
    }

    public function reject(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('update', $purchaseReturn);

        if (! in_array($purchaseReturn->status, ['requested', 'approved'], true)) {
            return ApiResponse::error('Only a requested or approved return can be rejected.', [], 422);
        }

        $this->transition($purchaseReturn, 'rejected', $request->input('note'));

        return ApiResponse::success(new PurchaseReturnResource($purchaseReturn->load(self::RELATIONS)), 'Purchase return rejected.');
    }

    public function shipBack(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('update', $purchaseReturn);

        if ($purchaseReturn->status !== 'approved') {
            return ApiResponse::error('Only an approved return can be marked shipped back to the supplier.', [], 422);
        }

        try {
            DB::transaction(function () use ($request, $purchaseReturn) {
                $purchaseOrder = $purchaseReturn->purchaseOrder;
                $items = $purchaseReturn->items()->with('purchaseOrderItem')->get();

                foreach ($items as $returnItem) {
                    $orderItem = $returnItem->purchaseOrderItem;

                    $level = StockLevel::query()
                        ->where('product_id', $orderItem->product_id)
                        ->where('product_variant_id', $orderItem->product_variant_id)
                        ->where('warehouse_id', $purchaseOrder->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    $before = $level?->quantity ?? 0;
                    $after = $before - $returnItem->quantity;

                    if ($after < 0) {
                        throw new InsufficientStockException(
                            "Stock for \"{$orderItem->product->name}\" is lower than this return's quantity — it may have already been sold, transferred, or adjusted away.",
                        );
                    }

                    $level->update(['quantity' => $after]);

                    StockMovement::create([
                        'store_id' => $purchaseReturn->store_id,
                        'product_id' => $orderItem->product_id,
                        'product_variant_id' => $orderItem->product_variant_id,
                        'warehouse_id' => $purchaseOrder->warehouse_id,
                        'type' => 'purchase_return',
                        'quantity' => $returnItem->quantity,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'reference_type' => PurchaseReturn::class,
                        'reference_id' => $purchaseReturn->id,
                        'created_by' => $request->user()->id,
                    ]);
                }

                $this->transition($purchaseReturn, 'shipped_back', $request->input('note'));
            });
        } catch (InsufficientStockException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(new PurchaseReturnResource($purchaseReturn->load(self::RELATIONS)), 'Purchase return marked shipped back to the supplier.');
    }

    public function credit(PurchaseReturnCreditRequest $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        $this->authorize('update', $purchaseReturn);

        if ($purchaseReturn->status !== 'shipped_back') {
            return ApiResponse::error('Only a return already shipped back can be marked credited.', [], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $purchaseReturn) {
            $creditAmount = isset($data['credit_amount'])
                ? Money::fromDecimal($data['credit_amount'], $purchaseReturn->purchaseOrder->currency_code)->amountMinor
                : $this->suggestedCreditAmount($purchaseReturn);

            $purchaseReturn->update(['credit_amount' => $creditAmount, 'credited_at' => now()]);

            $this->transition($purchaseReturn, 'credited', $data['note'] ?? null);
        });

        return ApiResponse::success(new PurchaseReturnResource($purchaseReturn->load(self::RELATIONS)), 'Purchase return credited.');
    }

    private function transition(PurchaseReturn $purchaseReturn, string $toStatus, ?string $note = null): void
    {
        $fromStatus = $purchaseReturn->status;
        $purchaseReturn->update(['status' => $toStatus]);

        $purchaseReturn->statusHistory()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_by' => request()->user()->id,
        ]);
    }

    private function suggestedCreditAmount(PurchaseReturn $purchaseReturn): int
    {
        return $purchaseReturn->items()->with('purchaseOrderItem')->get()
            ->sum(fn ($item) => $item->quantity * $item->purchaseOrderItem->unit_cost_amount);
    }
}
