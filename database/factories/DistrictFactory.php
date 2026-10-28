<?php

namespace Database\Factories;

use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'slug' => fake()->unique()->slug(),
            'risk_level' => fake()->randomElement(['safe', 'caution', 'risk', 'high_risk', 'critical', 'clearing']),
            'latitude' => fake()->latitude(-17.0, -11.0),
            'longitude' => fake()->longitude(-79.5, -68.0),
            'geojson_bounds' => [
                ['lat' => -16.5, 'lng' => -79.5],
                ['lat' => -11.0, 'lng' => -79.5],
                ['lat' => -11.0, 'lng' => -68.0],
                ['lat' => -16.5, 'lng' => -68.0],
            ],
            'description' => fake()->sentence(),
        ];
    }
}
