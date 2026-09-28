<?php

namespace Database\Factories;

use App\Models\Redirect;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'from_path' => '/'.fake()->unique()->slug(),
            'to_path' => '/'.fake()->slug(),
            'status_code' => 301,
            'hits_count' => 0,
        ];
    }
}
