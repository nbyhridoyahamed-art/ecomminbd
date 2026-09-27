<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BdDivision extends Model
{
    protected $fillable = ['name_en', 'name_bn', 'code'];

    public function districts(): HasMany
    {
        return $this->hasMany(BdDistrict::class);
    }
}
