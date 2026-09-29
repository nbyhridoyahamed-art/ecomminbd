<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\DeliveryZoneRequest;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Support\ApiResponse;
use App\Support\DeliveryRateResolver;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryZoneController extends Controller
{
    private const RELATIONS = ['division', 'district', 'rates'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryZone::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $zones = DeliveryZone::query()
            ->with(self::RELATIONS)
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            // Most specific first: an exact district match, then a division-wide
            // zone, then the store's fallback zone (both location fields null).
            ->orderByRaw('bd_division_id IS NULL, bd_district_id IS NULL')
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::success(
            DeliveryZoneResource::collection($zones),
            'Delivery zones fetched successfully.',
            [
                'current_page' => $zones->currentPage(),
                'per_page' => $zones->perPage(),
                'total' => $zones->total(),
                'last_page' => $zones->lastPage(),
            ],
        );
    }

    public function store(DeliveryZoneRequest $request): JsonResponse
    {
        $this->authorize('create', DeliveryZone::class);

        $data = $request->validated();

        $zone = DB::transaction(function () use ($data) {
            $zone = DeliveryZone::create([
                'store_id' => $data['store_id'],
                'name' => $data['name'],
                'bd_division_id' => $data['bd_division_id'] ?? null,
                'bd_district_id' => $data['bd_district_id'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->syncRates($zone, $data['rates']);

            return $zone;
        });

        return ApiResponse::success(new DeliveryZoneResource($zone->load(self::RELATIONS)), 'Delivery zone created successfully.', status: 201);
    }

    public function show(DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('view', $deliveryZone);

        return ApiResponse::success(new DeliveryZoneResource($deliveryZone->load(self::RELATIONS)), 'Delivery zone fetched successfully.');
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('update', $deliveryZone);

        $data = $request->validated();

        DB::transaction(function () use ($deliveryZone, $data) {
            $deliveryZone->update([
                'name' => $data['name'],
                'bd_division_id' => $data['bd_division_id'] ?? null,
                'bd_district_id' => $data['bd_district_id'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);

            $deliveryZone->rates()->delete();
            $this->syncRates($deliveryZone, $data['rates']);
        });

        return ApiResponse::success(new DeliveryZoneResource($deliveryZone->load(self::RELATIONS)), 'Delivery zone updated successfully.');
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $this->authorize('delete', $deliveryZone);

        $deliveryZone->delete();

        return ApiResponse::success(null, 'Delivery zone deleted successfully.');
    }

    /**
     * A shipping-fee preview for the admin order form's "Calculate" action.
     * Deliberately ungated beyond staff auth, same as LocationController —
     * any staff member creating/editing an order needs this, not just
     * whoever holds delivery_zones.view.
     */
    public function quote(Request $request): JsonResponse
    {
        $subtotalMinor = Money::fromDecimal($request->input('subtotal', 0))->amountMinor;

        $resolved = DeliveryRateResolver::resolve(
            $request->integer('store_id'),
            $request->filled('bd_division_id') ? $request->integer('bd_division_id') : null,
            $request->filled('bd_district_id') ? $request->integer('bd_district_id') : null,
            $subtotalMinor,
        );

        if (! $resolved) {
            return ApiResponse::success(['shipping_amount' => null, 'zone_name' => null], 'No delivery zone matches this location.');
        }

        return ApiResponse::success([
            'shipping_amount' => (new Money($resolved['rate_amount'], $resolved['currency_code']))->toDecimal(),
            'zone_name' => $resolved['zone']->name,
        ], 'Delivery quote calculated successfully.');
    }

    private function syncRates(DeliveryZone $zone, array $rates): void
    {
        foreach ($rates as $rate) {
            $zone->rates()->create([
                'min_order_subtotal_amount' => Money::fromDecimal($rate['min_order_subtotal'])->amountMinor,
                'rate_amount' => Money::fromDecimal($rate['rate_amount'])->amountMinor,
                'currency_code' => 'BDT',
            ]);
        }
    }
}
