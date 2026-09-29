<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $filename = Str::uuid().'.jpg';

        return [
            'store_id' => Store::factory(),
            'disk' => 'public',
            'path' => "media/{$filename}",
            'filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'alt_text' => fake()->sentence(3),
        ];
    }
}
