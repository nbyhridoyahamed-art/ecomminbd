<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purchase_return_id', 'from_status', 'to_status', 'note', 'created_by'])]
class PurchaseReturnStatusHistory extends Model
{
    use HasFactory;

    // Eloquent's pluralizer would guess "purchase_return_status_histories" —
    // the migration (matching order_status_history/return_status_history)
    // uses the singular.
    protected $table = 'purchase_return_status_history';

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
