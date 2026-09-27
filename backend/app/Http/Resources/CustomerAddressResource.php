<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'bd_division_id' => $this->bd_division_id,
            'bd_district_id' => $this->bd_district_id,
            'bd_upazila_id' => $this->bd_upazila_id,
            'division' => $this->whenLoaded('division', fn () => $this->division?->only(['id', 'name_en', 'name_bn'])),
            'district' => $this->whenLoaded('district', fn () => $this->district?->only(['id', 'name_en', 'name_bn'])),
            'upazila' => $this->whenLoaded('upazila', fn () => $this->upazila?->only(['id', 'name_en', 'name_bn'])),
            'is_default' => $this->is_default,
            'created_at' => $this->created_at,
        ];
    }
}
