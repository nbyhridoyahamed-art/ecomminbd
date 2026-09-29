<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Support\ApiResponse;
use App\Support\DeliveryRateResolver;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends StorefrontController
{
    /** A live shipping-fee preview as the shopper picks a division/district, before they submit checkout. */
    public function quote(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $subtotalMinor = Money::fromDecimal($request->input('subtotal', 0))->amountMinor;

        $resolved = DeliveryRateResolver::resolve(
            $store->id,
            $request->filled('bd_division_id') ? $request->integer('bd_division_id') : null,
            $request->filled('bd_district_id') ? $request->integer('bd_district_id') : null,
            $subtotalMinor,
        );

        return ApiResponse::success(
            ['shipping_amount' => $resolved ? (new Money($resolved['rate_amount'], $resolved['currency_code']))->toDecimal() : null],
            $resolved ? 'Delivery quote calculated successfully.' : 'No delivery zone matches this location.',
        );
    }
}
