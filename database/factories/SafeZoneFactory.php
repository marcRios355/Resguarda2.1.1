<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\SafeZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafeZone>
 */
class SafeZoneFactory extends Factory
{
    protected $model = SafeZone::class;

    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(-17.0, -11.0),
            'longitude' => fake()->longitude(-79.5, -68.0),
            'capacity' => fake()->numberBetween(20, 200),
            'current_occupancy' => fake()->numberBetween(0, 150),
            'status' => fake()->randomElement(['available', 'limited', 'almost_full', 'saturated', 'unavailable']),
            'services' => ['agua', 'luz', 'seguridad'],
            'image_path' => null,
            'notes' => fake()->sentence(),
            'is_verified' => true,
        ];
    }
}
