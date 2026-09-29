<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $coupons = Coupon::query()
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('code', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success(
            CouponResource::collection($coupons),
            'Coupons fetched successfully.',
            [
                'current_page' => $coupons->currentPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'last_page' => $coupons->lastPage(),
            ],
        );
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $coupon = Coupon::create($this->payload($request));

        return ApiResponse::success(new CouponResource($coupon), 'Coupon created successfully.', status: 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        $this->authorize('view', $coupon);

        return ApiResponse::success(new CouponResource($coupon), 'Coupon fetched successfully.');
    }

    public function update(CouponRequest $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        $coupon->update($this->payload($request));

        return ApiResponse::success(new CouponResource($coupon), 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return ApiResponse::success(message: 'Coupon deleted successfully.');
    }

    private function payload(CouponRequest $request): array
    {
        $data = $request->validated();
        $currency = $data['currency_code'] ?? 'BDT';

        return [
            'store_id' => $data['store_id'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'percentage_value' => $data['discount_type'] === 'percentage' ? $data['percentage_value'] : null,
            'fixed_discount_amount' => $data['discount_type'] === 'fixed'
                ? Money::fromDecimal($data['fixed_amount'], $currency)->amountMinor
                : null,
            'currency_code' => $currency,
            'minimum_order_amount' => Money::fromDecimal($data['minimum_order_amount'] ?? 0, $currency)->amountMinor,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }
}
