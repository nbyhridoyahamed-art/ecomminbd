<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bd_division_id' => $this->bd_division_id,
            'bd_district_id' => $this->bd_district_id,
            'division_name' => $this->whenLoaded('division', fn () => $this->division?->name_en),
            'district_name' => $this->whenLoaded('district', fn () => $this->district?->name_en),
            'status' => $this->status,
            'rates' => $this->whenLoaded('rates', fn () => $this->rates->map(fn ($rate) => [
                'id' => $rate->id,
                'min_order_subtotal' => (new Money($rate->min_order_subtotal_amount, $rate->currency_code))->toDecimal(),
                'rate_amount' => (new Money($rate->rate_amount, $rate->currency_code))->toDecimal(),
                'currency_code' => $rate->currency_code,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
