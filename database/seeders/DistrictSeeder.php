<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    /**
     * Seed the 12 official districts of Trujillo, La Libertad, Peru.
     * Coordenadas reales aproximadas de los centroides distritales.
     */
    public function run(): void
    {
        $districts = [
            [
                'name' => 'Trujillo',
                'latitude' => -8.1116,
                'longitude' => -79.0287,
                'risk_level' => 'safe',
                'description' => 'Distrito capital y centro histórico y administrativo de la provincia.',
            ],
            [
                'name' => 'Víctor Larco Herrera',
                'latitude' => -8.1362,
                'longitude' => -79.0494,
                'risk_level' => 'caution',
                'description' => 'Zona costera residencial. Riesgo moderado por cercanía al litoral y maretazos.',
            ],
            [
                'name' => 'Huanchaco',
                'latitude' => -8.0825,
                'longitude' => -79.1172,
                'risk_level' => 'safe',
                'description' => 'Balneario tradicional y zona arqueológica.',
            ],
            [
                'name' => 'El Porvenir',
                'latitude' => -8.0850,
                'longitude' => -79.0020,
                'risk_level' => 'risk',
                'description' => 'Zona este industrial de calzado. Vulnerable a activación de la quebrada San Ildefonso.',
            ],
            [
                'name' => 'La Esperanza',
                'latitude' => -8.0694,
                'longitude' => -79.0436,
                'risk_level' => 'caution',
                'description' => 'Distrito norte con alta densidad poblacional. Puntos críticos en vías principales.',
            ],
            [
                'name' => 'Florencia de Mora',
                'latitude' => -8.0858,
                'longitude' => -79.0270,
                'risk_level' => 'caution',
                'description' => 'Distrito intermedio con pendientes hacia quebradas.',
            ],
            [
                'name' => 'Moche',
                'latitude' => -8.1711,
                'longitude' => -79.0094,
                'risk_level' => 'safe',
                'description' => 'Cuna de la cultura Moche y cuenca baja del río Moche.',
            ],
            [
                'name' => 'Salaverry',
                'latitude' => -8.2236,
                'longitude' => -78.9767,
                'risk_level' => 'safe',
                'description' => 'Puerto marítimo de La Libertad.',
            ],
            [
                'name' => 'Laredo',
                'latitude' => -8.0897,
                'longitude' => -78.9603,
                'risk_level' => 'risk',
                'description' => 'Valle agrícola y azucarero. Vulnerable ante desbordes del río Moche y quebradas.',
            ],
            [
                'name' => 'Simbal',
                'latitude' => -7.9744,
                'longitude' => -78.8153,
                'risk_level' => 'caution',
                'description' => 'Distrito en quebrada intermedia camino a la sierra liberteña.',
            ],
            [
                'name' => 'Poroto',
                'latitude' => -8.0167,
                'longitude' => -78.7833,
                'risk_level' => 'caution',
                'description' => 'Zona de valle andino y producción de frutas. Susceptible a deslizamientos.',
            ],
            [
                'name' => 'Alto Trujillo',
                'latitude' => -8.0670,
                'longitude' => -78.9880,
                'risk_level' => 'risk',
                'description' => 'Distrito joven en laderas orientales. Vulnerabilidad ante lluvias intensas.',
            ],
        ];

        foreach ($districts as $item) {
            District::updateOrCreate(
                ['slug' => Str::slug($item['name'])],
                [
                    'name' => $item['name'],
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                    'risk_level' => $item['risk_level'],
                    'description' => $item['description'],
                ]
            );
        }
    }
}
