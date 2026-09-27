<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'label', 'recipient_name', 'phone', 'address_line',
    'bd_division_id', 'bd_district_id', 'bd_upazila_id', 'is_default',
])]
class CustomerAddress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(BdDivision::class, 'bd_division_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(BdDistrict::class, 'bd_district_id');
    }

    public function upazila(): BelongsTo
    {
        return $this->belongsTo(BdUpazila::class, 'bd_upazila_id');
    }
}
