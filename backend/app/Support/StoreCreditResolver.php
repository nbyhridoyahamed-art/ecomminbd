<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerStoreCredit;
use App\Models\Order;
use App\Models\OrderReturn;

/**
 * Validates and applies a customer's store credit — mirrors CouponResolver's
 * shape (resolve() then a follow-up write, both inside the caller's DB
 * transaction) but for the append-only customer_store_credits ledger rather
 * than a single coupon row. A customer's balance is never stored, always
 * sum(amount) — resolve() locks the customer row first so two concurrent
 * orders can't both spend the same credit twice.
 *
 * Admin order creation only: the storefront's guest checkout matches a
 * customer by phone number without authenticating them, so it can never
 * safely trust a request to spend someone else's balance.
 */
final class StoreCreditResolver
{
    /** Resolves a requested decimal amount to the minor-unit amount actually redeemable, clamped to the order's cap. */
    public static function resolve(int $customerId, ?float $requestedAmount, string $currency, int $capMinor): int
    {
        if (! $requestedAmount || $requestedAmount <= 0 || $capMinor <= 0) {
            return 0;
        }

        $customer = Customer::query()->where('id', $customerId)->lockForUpdate()->first();
        $requestedMinor = Money::fromDecimal($requestedAmount, $currency)->amountMinor;
        $balance = (int) $customer->storeCredits()->sum('amount');

        if ($requestedMinor > $balance) {
            $available = (new Money($balance, $currency))->toDecimal();
            throw new StoreCreditException("This customer only has {$available} {$currency} of store credit available.");
        }

        return min($requestedMinor, $capMinor);
    }

    public static function redeem(int $customerId, int $storeId, int $amountMinor, Order $order, ?int $createdBy): void
    {
        if ($amountMinor <= 0) {
            return;
        }

        CustomerStoreCredit::create([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'amount' => -$amountMinor,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'note' => "Redeemed on order {$order->order_number}",
            'created_by' => $createdBy,
        ]);
    }

    /** Reverses a previously-redeemed amount (an edited-away or cancelled order) — an append-only ledger never deletes the original entry. */
    public static function release(Order $order, ?int $createdBy): void
    {
        if ($order->store_credit_amount <= 0) {
            return;
        }

        CustomerStoreCredit::create([
            'store_id' => $order->store_id,
            'customer_id' => $order->customer_id,
            'amount' => $order->store_credit_amount,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'note' => "Reversed — order {$order->order_number} updated or cancelled",
            'created_by' => $createdBy,
        ]);
    }

    public static function issue(int $customerId, int $storeId, int $amountMinor, OrderReturn $orderReturn, ?int $createdBy): void
    {
        if ($amountMinor <= 0) {
            return;
        }

        CustomerStoreCredit::create([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'amount' => $amountMinor,
            'reference_type' => OrderReturn::class,
            'reference_id' => $orderReturn->id,
            'note' => "Store credit from return {$orderReturn->return_number}",
            'created_by' => $createdBy,
        ]);
    }
}
