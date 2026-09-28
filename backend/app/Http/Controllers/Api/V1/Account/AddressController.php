<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerAddress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The self-service mirror of Api\V1\CustomerAddressController — same
 * CustomerAddressRequest/CustomerAddressResource and default-address
 * logic, but always scoped to the authenticated customer rather than a
 * route-bound {customer}, since a customer here can only ever act on
 * their own addresses.
 */
class AddressController extends Controller
{
    private const RELATIONS = ['division', 'district', 'upazila'];

    public function index(): JsonResponse
    {
        $addresses = Auth::user()->addresses()->with(self::RELATIONS)->get();

        return ApiResponse::success(CustomerAddressResource::collection($addresses), 'Addresses fetched successfully.');
    }

    public function store(CustomerAddressRequest $request): JsonResponse
    {
        $customer = Auth::user();
        $data = $request->validated();

        $address = DB::transaction(function () use ($customer, $data) {
            if ($data['is_default'] ?? false) {
                $customer->addresses()->update(['is_default' => false]);
            } elseif ($customer->addresses()->count() === 0) {
                $data['is_default'] = true;
            }

            return $customer->addresses()->create($data);
        });

        return ApiResponse::success(new CustomerAddressResource($address->load(self::RELATIONS)), 'Address added successfully.', status: 201);
    }

    public function update(CustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $customer = Auth::user();

        if ($address->customer_id !== $customer->id) {
            return ApiResponse::error('The requested resource was not found.', [], 404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($customer, $address, $data) {
            if ($data['is_default'] ?? false) {
                $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return ApiResponse::success(new CustomerAddressResource($address->load(self::RELATIONS)), 'Address updated successfully.');
    }

    public function destroy(CustomerAddress $address): JsonResponse
    {
        $customer = Auth::user();

        if ($address->customer_id !== $customer->id) {
            return ApiResponse::error('The requested resource was not found.', [], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $customer->addresses()->first()?->update(['is_default' => true]);
        }

        return ApiResponse::success(message: 'Address deleted successfully.');
    }
}
