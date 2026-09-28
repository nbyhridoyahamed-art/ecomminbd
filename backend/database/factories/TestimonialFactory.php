<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Verified Customer', 'Repeat Customer', null]),
            'quote' => fake()->paragraph(2),
            'avatar_url' => null,
            'rating' => fake()->numberBetween(3, 5),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
