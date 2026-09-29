<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_transfer_id', 'from_status', 'to_status', 'note', 'created_by'])]
class StockTransferStatusHistory extends Model
{
    use HasFactory;

    // Eloquent's pluralizer would guess "stock_transfer_status_histories" —
    // the migration (matching shipment_status_history's naming) uses the
    // singular.
    protected $table = 'stock_transfer_status_history';

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
