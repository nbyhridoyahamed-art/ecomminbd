<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The supplier ledger (Phase 7 Wave 2) reconciles what a supplier is owed
 * against what's been paid or credited — not a general-purpose accounting
 * module, just enough to answer "how much do we currently owe this
 * supplier." Debits are recognized per `purchase_receipts` row (goods
 * actually received — not the whole PO total, which would overstate the
 * liability on a still partially_received order); credits are
 * `supplier_payments` (cash out) and any `purchase_returns` already
 * `credited` (goods sent back, see PurchaseReturnController::credit()).
 */
class SupplierPaymentController extends Controller
{
    public function ledger(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $currencyCode = $supplier->store?->currency?->code ?? 'BDT';

        $receiptRows = DB::table('purchase_receipts')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_receipts.purchase_order_id')
            ->join('purchase_receipt_items', 'purchase_receipt_items.purchase_receipt_id', '=', 'purchase_receipts.id')
            ->join('purchase_order_items', 'purchase_order_items.id', '=', 'purchase_receipt_items.purchase_order_item_id')
            ->where('purchase_orders.supplier_id', $supplier->id)
            ->selectRaw(
                'purchase_receipts.id, purchase_receipts.receipt_number, purchase_receipts.created_at, '.
                'purchase_orders.po_number, SUM(purchase_receipt_items.quantity_received * purchase_order_items.unit_cost_amount) as amount_minor',
            )
            ->groupBy('purchase_receipts.id', 'purchase_receipts.receipt_number', 'purchase_receipts.created_at', 'purchase_orders.po_number')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->created_at),
                'type' => 'receipt',
                'reference' => $row->receipt_number,
                'description' => "Goods received against {$row->po_number}",
                'debit_amount' => (new Money((int) $row->amount_minor, $currencyCode))->toDecimal(),
                'credit_amount' => null,
            ]);

        $creditRows = DB::table('purchase_returns')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_returns.purchase_order_id')
            ->where('purchase_orders.supplier_id', $supplier->id)
            ->where('purchase_returns.status', 'credited')
            ->select(
                'purchase_returns.id', 'purchase_returns.return_number', 'purchase_returns.credited_at',
                'purchase_returns.credit_amount', 'purchase_orders.po_number',
            )
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->credited_at),
                'type' => 'credit',
                'reference' => $row->return_number,
                'description' => "Return credited against {$row->po_number}",
                'debit_amount' => null,
                'credit_amount' => (new Money((int) $row->credit_amount, $currencyCode))->toDecimal(),
            ]);

        $paymentRows = SupplierPayment::query()
            ->where('supplier_id', $supplier->id)
            ->with('purchaseOrder:id,po_number')
            ->get()
            ->map(fn (SupplierPayment $payment) => [
                'date' => $payment->created_at,
                'type' => 'payment',
                'reference' => $payment->reference,
                'description' => $payment->purchaseOrder
                    ? "Payment against {$payment->purchaseOrder->po_number} ({$payment->method})"
                    : "Payment ({$payment->method})",
                'debit_amount' => null,
                'credit_amount' => (new Money($payment->amount_amount, $payment->currency_code))->toDecimal(),
            ]);

        $entries = $receiptRows->concat($creditRows)->concat($paymentRows)
            ->sortBy('date')
            ->values();

        $balance = 0;
        $entries = $entries->map(function ($entry) use (&$balance) {
            $balance += ($entry['debit_amount'] ?? 0) - ($entry['credit_amount'] ?? 0);
            $entry['running_balance'] = round($balance, 2);

            return $entry;
        });

        return ApiResponse::success([
            'currency_code' => $currencyCode,
            'balance_amount' => round($balance, 2),
            'entries' => $entries,
        ], 'Supplier ledger fetched successfully.');
    }

    public function store(SupplierPaymentRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('pay', $supplier);

        $data = $request->validated();
        $currencyCode = $supplier->store?->currency?->code ?? 'BDT';

        $payment = SupplierPayment::create([
            'store_id' => $supplier->store_id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'amount_amount' => Money::fromDecimal($data['amount'], $currencyCode)->amountMinor,
            'currency_code' => $currencyCode,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success([
            'id' => $payment->id,
            'amount' => (new Money($payment->amount_amount, $payment->currency_code))->toDecimal(),
            'method' => $payment->method,
            'created_at' => $payment->created_at,
        ], 'Payment recorded successfully.', status: 201);
    }
}
