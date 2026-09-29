<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'has_account' => $this->password !== null,
            'orders_count' => $this->whenCounted('orders'),
            // Only present when the controller eager-sums it (index/show) —
            // never a stored column, see Customer::storeCreditBalance().
            'store_credit_balance' => $this->store_credit_balance_minor === null
                ? null
                : (new Money((int) $this->store_credit_balance_minor, 'BDT'))->toDecimal(),
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
