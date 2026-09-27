<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BdUpazila extends Model
{
    protected $fillable = ['bd_district_id', 'name_en', 'name_bn', 'code'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(BdDistrict::class, 'bd_district_id');
    }
}
