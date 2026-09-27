<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipment_id', 'from_status', 'to_status', 'note', 'created_by'])]
class ShipmentStatusHistory extends Model
{
    use HasFactory;

    // Eloquent's pluralizer would guess "shipment_status_histories" — the
    // migration (matching order_status_history's naming) uses the singular.
    protected $table = 'shipment_status_history';

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
