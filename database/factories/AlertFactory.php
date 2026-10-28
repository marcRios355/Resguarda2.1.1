<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'district_id' => District::factory(),
            'type' => fake()->randomElement([
                'sismo',
                'inundacion',
                'huaico',
                'lluvia_intensa',
                'desborde',
                'alerta_general',
            ]),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'severity' => fake()->randomElement(['info', 'caution', 'danger', 'critical']),
            'latitude' => fake()->latitude(-17.0, -11.0),
            'longitude' => fake()->longitude(-79.5, -68.0),
            'radius_meters' => fake()->numberBetween(100, 1000),
            'is_active' => true,
            'expires_at' => now()->addHours(12),
        ];
    }
}
