<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * created_at/updated_at are deliberately absent from every model's
 * #[Fillable] list in this app (never mass-assignable), so passing them
 * inside a ::create([...]) array is silently dropped and the row gets
 * "now" regardless. forceFill() bypasses the fillable check and marks the
 * columns dirty, which makes Model::save()'s own updateTimestamps() skip
 * them (it only touches a timestamp that isn't already dirty) — the one
 * combination that actually sticks.
 */
trait BackdatesTimestamps
{
    private function backdate(Model $model, Carbon $at): Model
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at]);
        $model->save();

        return $model;
    }
}
