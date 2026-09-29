<?php

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerStoreCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Derived from the ledger's own sign, never stored — positive
            // entries are credit issued, negative are redeemed or reversed.
            'type' => $this->amount >= 0 ? 'issued' : 'redeemed',
            'amount' => (new Money(abs($this->amount), 'BDT'))->toDecimal(),
            'note' => $this->note,
            'created_by' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
