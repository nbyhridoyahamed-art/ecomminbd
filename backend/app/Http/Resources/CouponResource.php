<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'percentage_value' => $this->percentage_value,
            'fixed_amount' => $this->fixed_discount_amount === null
                ? null
                : (new Money($this->fixed_discount_amount, $this->currency_code))->toDecimal(),
            'currency_code' => $this->currency_code,
            'minimum_order_amount' => (new Money($this->minimum_order_amount, $this->currency_code))->toDecimal(),
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'per_customer_limit' => $this->per_customer_limit,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
