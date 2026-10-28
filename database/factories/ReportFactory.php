<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'district_id' => District::factory(),
            'incident_type' => fake()->randomElement([
                'inundacion',
                'huaico',
                'desborde',
                'calle_bloqueada',
                'zona_en_riesgo',
                'zona_segura',
                'personas_atrapadas',
                'zona_despejada',
                'zona_en_proceso_despeje',
                'otro',
            ]),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'latitude' => fake()->latitude(-17.0, -11.0),
            'longitude' => fake()->longitude(-79.5, -68.0),
            'address_reference' => fake()->streetAddress(),
            'photo_path' => null,
            'severity' => fake()->randomElement(['baja', 'media', 'alta', 'critica']),
            'status' => fake()->randomElement(['pending', 'community_alert', 'confirmed', 'rejected', 'resolved']),
            'confirmations_count' => 0,
            'rejections_count' => 0,
        ];
    }
}
