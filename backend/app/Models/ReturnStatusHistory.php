<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_id', 'from_status', 'to_status', 'note', 'created_by'])]
class ReturnStatusHistory extends Model
{
    use HasFactory;

    // Eloquent's pluralizer would guess "return_status_histories" — the
    // migration (matching order_status_history/shipment_status_history)
    // uses the singular.
    protected $table = 'return_status_history';

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
