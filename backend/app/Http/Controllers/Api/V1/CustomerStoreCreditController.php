<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerStoreCreditResource;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerStoreCreditController extends Controller
{
    /**
     * Read-only: the ledger is only ever appended to as a side effect of a
     * return refund (issuance) or an order create/update/cancel
     * (redemption/reversal) — see StoreCreditResolver — never written to
     * directly, so there's no store()/manual-issue endpoint here.
     */
    public function index(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $perPage = min((int) $request->integer('per_page', 20), 100);

        $credits = $customer->storeCredits()->with('creator')->latest()->paginate($perPage);

        return ApiResponse::success(
            CustomerStoreCreditResource::collection($credits),
            'Store credit ledger fetched successfully.',
            [
                'current_page' => $credits->currentPage(),
                'per_page' => $credits->perPage(),
                'total' => $credits->total(),
                'last_page' => $credits->lastPage(),
                'balance' => (new Money($customer->storeCreditBalance(), 'BDT'))->toDecimal(),
            ],
        );
    }
}
