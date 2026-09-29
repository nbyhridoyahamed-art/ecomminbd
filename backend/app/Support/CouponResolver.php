<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;

/**
 * Validates and applies a coupon code — the one place that happens, shared
 * by the admin OrderController and the public storefront CheckoutController
 * so the two entry points can never drift on what makes a coupon valid.
 * Callers must run resolve()/recordUsage() inside a DB transaction: resolve()
 * locks the coupon row so two concurrent orders can't both claim the last
 * use of a limited coupon.
 */
final class CouponResolver
{
    /** @return array{coupon: Coupon, discount_amount: int} */
    public static function resolve(int $storeId, string $code, int $subtotalMinor, ?int $customerId): array
    {
        $coupon = Coupon::query()
            ->where('store_id', $storeId)
            ->where('code', strtoupper(trim($code)))
            ->lockForUpdate()
            ->first();

        if (! $coupon) {
            throw new CouponException('This coupon code is not valid.');
        }

        if ($coupon->status !== 'active') {
            throw new CouponException('This coupon is no longer active.');
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new CouponException('This coupon is not active yet.');
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw new CouponException('This coupon has expired.');
        }

        if ($subtotalMinor < $coupon->minimum_order_amount) {
            $minimum = (new Money($coupon->minimum_order_amount, $coupon->currency_code))->toDecimal();
            throw new CouponException("This coupon requires a minimum order of {$minimum} {$coupon->currency_code}.");
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new CouponException('This coupon has reached its usage limit.');
        }

        if ($customerId !== null && $coupon->per_customer_limit !== null) {
            $alreadyUsed = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->count();

            if ($alreadyUsed >= $coupon->per_customer_limit) {
                throw new CouponException('You have already used this coupon the maximum number of times.');
            }
        }

        $discount = $coupon->discount_type === 'percentage'
            ? (int) floor($subtotalMinor * $coupon->percentage_value / 100)
            : min($coupon->fixed_discount_amount, $subtotalMinor);

        return ['coupon' => $coupon, 'discount_amount' => $discount];
    }

    public static function recordUsage(Coupon $coupon, Order $order, int $discountMinor): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'discount_amount' => $discountMinor,
            'currency_code' => $coupon->currency_code,
        ]);

        $coupon->increment('used_count');
    }

    /** Releases a cancelled/edited-away order's coupon usage, freeing it up for reuse. */
    public static function releaseUsage(Order $order): void
    {
        $usage = CouponUsage::query()->where('order_id', $order->id)->first();

        if (! $usage) {
            return;
        }

        if ($usage->coupon_id !== null) {
            Coupon::query()->where('id', $usage->coupon_id)->lockForUpdate()->decrement('used_count');
        }

        $usage->delete();
    }
}
