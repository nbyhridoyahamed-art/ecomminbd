<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BdDistrict extends Model
{
    protected $fillable = ['bd_division_id', 'name_en', 'name_bn', 'code'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(BdDivision::class, 'bd_division_id');
    }

    public function upazilas(): HasMany
    {
        return $this->hasMany(BdUpazila::class);
    }
}
