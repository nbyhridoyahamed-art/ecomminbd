<?php

namespace App\Support;

use App\Models\DeliveryZone;

/**
 * Resolves the shipping charge for a shipping location + order subtotal —
 * the one place that happens, shared by the admin DeliveryZoneController's
 * quote action, the storefront's own quote action, and the storefront
 * CheckoutController itself, the same "one resolver, two producers" shape
 * CouponResolver already established. Returns null when no zone is
 * configured for the location at all (a store that hasn't set up delivery
 * zones yet), so callers fall back to Wave 1's plain manual/free entry
 * rather than blocking order placement on missing configuration.
 */
final class DeliveryRateResolver
{
    /** @return array{zone: DeliveryZone, rate_amount: int, currency_code: string}|null */
    public static function resolve(int $storeId, ?int $divisionId, ?int $districtId, int $subtotalMinor): ?array
    {
        $zone = self::matchZone($storeId, $divisionId, $districtId);

        if (! $zone) {
            return null;
        }

        // reorder(), not orderByDesc() — DeliveryZone::rates() already applies
        // its own ascending orderBy(), which a chained orderByDesc() on the
        // same column doesn't replace (it appends a second, ineffective sort
        // key), so first() would keep returning the lowest tier regardless
        // of subtotal.
        $rate = $zone->rates()
            ->where('min_order_subtotal_amount', '<=', $subtotalMinor)
            ->reorder('min_order_subtotal_amount', 'desc')
            ->first();

        if (! $rate) {
            return null;
        }

        return ['zone' => $zone, 'rate_amount' => $rate->rate_amount, 'currency_code' => $rate->currency_code];
    }

    /** Most specific match wins: exact district, then division-wide, then the store's fallback zone (both location fields null). */
    private static function matchZone(int $storeId, ?int $divisionId, ?int $districtId): ?DeliveryZone
    {
        $base = DeliveryZone::query()->where('store_id', $storeId)->where('status', 'active');

        if ($districtId !== null) {
            $match = (clone $base)->where('bd_division_id', $divisionId)->where('bd_district_id', $districtId)->first();

            if ($match) {
                return $match;
            }
        }

        if ($divisionId !== null) {
            $match = (clone $base)->where('bd_division_id', $divisionId)->whereNull('bd_district_id')->first();

            if ($match) {
                return $match;
            }
        }

        return (clone $base)->whereNull('bd_division_id')->whereNull('bd_district_id')->first();
    }
}
