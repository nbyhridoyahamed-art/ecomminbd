<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'store_id', 'order_number', 'customer_id', 'warehouse_id', 'status',
    'payment_method', 'payment_status', 'currency_code', 'shipping_amount', 'discount_amount',
    'customer_address_id', 'shipping_recipient_name', 'shipping_phone', 'shipping_address_line',
    'shipping_bd_division_id', 'shipping_bd_district_id', 'shipping_bd_upazila_id',
    'notes', 'created_by',
])]
class Order extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            $order->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function shippingDivision(): BelongsTo
    {
        return $this->belongsTo(BdDivision::class, 'shipping_bd_division_id');
    }

    public function shippingDistrict(): BelongsTo
    {
        return $this->belongsTo(BdDistrict::class, 'shipping_bd_district_id');
    }

    public function shippingUpazila(): BelongsTo
    {
        return $this->belongsTo(BdUpazila::class, 'shipping_bd_upazila_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
