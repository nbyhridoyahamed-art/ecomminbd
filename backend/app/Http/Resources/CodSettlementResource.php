<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CodSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'settlement_number' => $this->settlement_number,
            'amount_expected' => (new Money($this->amount_expected))->toDecimal(),
            'amount_received' => (new Money($this->amount_received))->toDecimal(),
            'note' => $this->note,
            'courier' => $this->whenLoaded('courier', fn () => ['id' => $this->courier->id, 'name' => $this->courier->name]),
            'shipments' => $this->whenLoaded('shipments', fn () => $this->shipments->map(fn ($shipment) => [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'order_number' => $shipment->order->order_number,
                'cod_amount_collected' => (new Money($shipment->cod_amount_collected ?? 0))->toDecimal(),
            ])),
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
