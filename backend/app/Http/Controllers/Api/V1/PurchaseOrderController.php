<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    // Every response that represents "a purchase order" loads the same
    // relations, including receipts — otherwise a resource built from a
    // partially-loaded model would serialize with `receipts` missing
    // entirely (JsonResource::whenLoaded), and a frontend cache write
    // from that response (e.g. after approve()/cancel()) would silently
    // drop a field the show() response always includes.
    private const RELATIONS = [
        'warehouse', 'supplier', 'items.product', 'items.productVariant.attributeValues.attribute', 'creator',
        'receipts.items.orderItem.product', 'receipts.items.orderItem.productVariant', 'receipts.receiver',
        'returns', 'statusHistory.creator', 'payments.creator',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $orders = PurchaseOrder::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            // "Open" = not yet fully received or cancelled — backs the dashboard's open-PO count.
            ->when($request->boolean('open'), fn ($query) => $query->whereIn('status', ['draft', 'pending_approval', 'ordered', 'partially_received']))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            PurchaseOrderResource::collection($orders),
            'Purchase orders fetched successfully.',
            [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        );
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $data = $request->validated();
        $currency = $data['currency_code'] ?? 'BDT';

        $order = DB::transaction(function () use ($data, $currency, $request) {
            $order = PurchaseOrder::create([
                'store_id' => $data['store_id'],
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'po_number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'draft',
                'currency_code' => $currency,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($order, $data['items'], $currency);

            return $order;
        });

        return ApiResponse::success(new PurchaseOrderResource($order->load(self::RELATIONS)), 'Purchase order created successfully.', status: 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return ApiResponse::success(
            new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)),
            'Purchase order fetched successfully.',
        );
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            return ApiResponse::error('Only draft purchase orders can be edited.', [], 422);
        }

        $data = $request->validated();
        $currency = $data['currency_code'] ?? $purchaseOrder->currency_code;

        DB::transaction(function () use ($purchaseOrder, $data, $currency) {
            $purchaseOrder->update([
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'currency_code' => $currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $data['items'], $currency);
        });

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)), 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            return ApiResponse::error('Only draft purchase orders can be deleted.', [], 422);
        }

        $purchaseOrder->delete();

        return ApiResponse::success(message: 'Purchase order deleted successfully.');
    }

    /** draft -> pending_approval. No stock or supplier-facing effect yet — approve() is the moment this actually commits to the supplier. */
    public function submitForApproval(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            return ApiResponse::error('Only draft purchase orders can be submitted for approval.', [], 422);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return ApiResponse::error('Add at least one item before submitting this order for approval.', [], 422);
        }

        $this->transition($purchaseOrder, 'pending_approval');

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)), 'Purchase order submitted for approval.');
    }

    /** pending_approval -> ordered — the real "committed to the supplier" moment, gated behind a permission distinct from create/update so the approver need not be the requester. */
    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        if ($purchaseOrder->status !== 'pending_approval') {
            return ApiResponse::error('Only a purchase order pending approval can be approved.', [], 422);
        }

        $this->transition($purchaseOrder, 'ordered');

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)), 'Purchase order approved and placed.');
    }

    /** pending_approval -> draft — reopens the order for editing rather than killing it, since a rejection is usually "fix this and resubmit," not "abandon it" (that's what cancel() is for). */
    public function reject(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        if ($purchaseOrder->status !== 'pending_approval') {
            return ApiResponse::error('Only a purchase order pending approval can be rejected.', [], 422);
        }

        $this->transition($purchaseOrder, 'draft', $request->input('note'));

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)), 'Purchase order rejected and reopened for editing.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        if (! in_array($purchaseOrder->status, ['draft', 'pending_approval', 'ordered'], true)) {
            return ApiResponse::error('Only a draft, pending-approval, or ordered purchase order can be cancelled.', [], 422);
        }

        $this->transition($purchaseOrder, 'cancelled');

        return ApiResponse::success(new PurchaseOrderResource($purchaseOrder->load(self::RELATIONS)), 'Purchase order cancelled successfully.');
    }

    private function transition(PurchaseOrder $purchaseOrder, string $toStatus, ?string $note = null): void
    {
        $fromStatus = $purchaseOrder->status;
        $purchaseOrder->update(['status' => $toStatus]);

        $purchaseOrder->statusHistory()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_by' => request()->user()->id,
        ]);
    }

    /** Replaces a draft order's items wholesale — see PurchaseOrderRequest for why a partial PATCH isn't offered. */
    private function syncItems(PurchaseOrder $order, array $items, string $currency): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity_ordered' => $item['quantity_ordered'],
                'unit_cost_amount' => Money::fromDecimal($item['unit_cost'], $currency)->amountMinor,
            ]);
        }
    }
}
