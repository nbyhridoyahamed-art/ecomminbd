<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseReceiptRequest;
use App\Http\Resources\PurchaseReceiptResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Support\OverReceiptException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReceiptController extends Controller
{
    public function store(PurchaseReceiptRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $request->user()->can('purchase_orders.receive')) {
            throw new AuthorizationException;
        }

        if (! in_array($purchaseOrder->status, ['ordered', 'partially_received'], true)) {
            return ApiResponse::error('This purchase order is not open to receive stock against.', [], 422);
        }

        $data = $request->validated();

        try {
            $receipt = DB::transaction(function () use ($purchaseOrder, $data, $request) {
                $receipt = PurchaseReceipt::create([
                    'store_id' => $purchaseOrder->store_id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'receipt_number' => 'GRN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'note' => $data['note'] ?? null,
                    'received_by' => $request->user()->id,
                ]);

                foreach ($data['items'] as $itemInput) {
                    $orderItem = PurchaseOrderItem::query()
                        ->lockForUpdate()
                        ->findOrFail($itemInput['purchase_order_item_id']);

                    $remaining = $orderItem->quantityRemaining();
                    if ($itemInput['quantity_received'] > $remaining) {
                        throw new OverReceiptException(
                            "Cannot receive {$itemInput['quantity_received']} of \"{$orderItem->product->name}\" — only {$remaining} remain on this order.",
                        );
                    }

                    $orderItem->update(['quantity_received' => $orderItem->quantity_received + $itemInput['quantity_received']]);

                    PurchaseReceiptItem::create([
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $orderItem->id,
                        'quantity_received' => $itemInput['quantity_received'],
                    ]);

                    $this->receiveStock($purchaseOrder, $orderItem, $itemInput['quantity_received'], $request->user()->id, $receipt);
                }

                $allReceived = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->whereColumn('quantity_received', '<', 'quantity_ordered')
                    ->doesntExist();

                $fromStatus = $purchaseOrder->status;
                $toStatus = $allReceived ? 'received' : 'partially_received';
                $purchaseOrder->update(['status' => $toStatus]);
                $purchaseOrder->statusHistory()->create([
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'note' => "Receipt {$receipt->receipt_number}",
                    'created_by' => $request->user()->id,
                ]);

                return $receipt;
            });
        } catch (OverReceiptException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }

        return ApiResponse::success(
            new PurchaseReceiptResource($receipt->load(['items.orderItem.product', 'items.orderItem.productVariant', 'receiver'])),
            'Receipt recorded successfully.',
            status: 201,
        );
    }

    /** Every receipt line increases stock — the same locked read/write pattern as StockTransferController, minus the negative-quantity guard since receiving never decreases anything. */
    private function receiveStock(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $orderItem,
        int $quantity,
        int $userId,
        PurchaseReceipt $receipt,
    ): void {
        $level = StockLevel::query()
            ->where('product_id', $orderItem->product_id)
            ->where('product_variant_id', $orderItem->product_variant_id)
            ->where('warehouse_id', $purchaseOrder->warehouse_id)
            ->lockForUpdate()
            ->first();

        $before = $level?->quantity ?? 0;
        $after = $before + $quantity;

        $level
            ? $level->update(['quantity' => $after])
            : StockLevel::create([
                'product_id' => $orderItem->product_id,
                'product_variant_id' => $orderItem->product_variant_id,
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'quantity' => $after,
            ]);

        StockMovement::create([
            'store_id' => $purchaseOrder->store_id,
            'product_id' => $orderItem->product_id,
            'product_variant_id' => $orderItem->product_variant_id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'type' => 'purchase_receipt',
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => PurchaseReceipt::class,
            'reference_id' => $receipt->id,
            'created_by' => $userId,
        ]);
    }
}
