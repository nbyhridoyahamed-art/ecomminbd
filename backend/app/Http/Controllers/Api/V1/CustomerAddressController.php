<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    private const RELATIONS = ['division', 'district', 'upazila'];

    public function store(CustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validated();

        $address = DB::transaction(function () use ($customer, $data) {
            if ($data['is_default'] ?? false) {
                $customer->addresses()->update(['is_default' => false]);
            } elseif ($customer->addresses()->count() === 0) {
                // The customer's first address is the default one automatically.
                $data['is_default'] = true;
            }

            return $customer->addresses()->create($data);
        });

        return ApiResponse::success(new CustomerAddressResource($address->load(self::RELATIONS)), 'Address added successfully.', status: 201);
    }

    public function update(CustomerAddressRequest $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $customer);

        if ($address->customer_id !== $customer->id) {
            return ApiResponse::error('This address does not belong to this customer.', [], 404);
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

    public function destroy(Customer $customer, CustomerAddress $address): JsonResponse
    {
        $this->authorize('update', $customer);

        if ($address->customer_id !== $customer->id) {
            return ApiResponse::error('This address does not belong to this customer.', [], 404);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = $customer->addresses()->first();
            $next?->update(['is_default' => true]);
        }

        return ApiResponse::success(message: 'Address deleted successfully.');
    }
}
