<?php

namespace Database\Factories;

use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SosAlert>
 */
class SosAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'latitude' => null,
            'longitude' => null,
            'delivery_status' => 'registered',
            'idempotency_key' => null,
        ];
    }
}
