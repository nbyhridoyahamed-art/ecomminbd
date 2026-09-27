<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'manager_name' => $this->manager_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'division' => $this->whenLoaded('division', fn () => $this->division?->only(['id', 'name_en', 'name_bn'])),
            'district' => $this->whenLoaded('district', fn () => $this->district?->only(['id', 'name_en', 'name_bn'])),
            'upazila' => $this->whenLoaded('upazila', fn () => $this->upazila?->only(['id', 'name_en', 'name_bn'])),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
