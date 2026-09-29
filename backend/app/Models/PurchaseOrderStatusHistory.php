<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_order_id', 'from_status', 'to_status', 'note', 'created_by'])]
class PurchaseOrderStatusHistory extends Model
{
    use HasFactory;

    // Eloquent's pluralizer would guess "purchase_order_status_histories" —
    // matches the singular convention every other *_status_history table uses.
    protected $table = 'purchase_order_status_history';

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
