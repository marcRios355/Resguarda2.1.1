<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\SafeZone;
use Illuminate\Database\Seeder;

class SafeZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * DATOS DE DEMOSTRACIÓN - no representan ubicaciones oficiales reales ni capacidades reales de refugios.
     */
    public function run(): void
    {
        $districts = District::all()->keyBy('slug');

        $zones = [
            [
                'district' => 'trujillo',
                'name' => 'Coliseo Municipal El Milagro',
                'address' => 'Av. España 245, Trujillo',
                'latitude' => -8.1123,
                'longitude' => -79.0306,
                'capacity' => 220,
                'current_occupancy' => 68,
                'status' => 'available',
                'services' => ['agua', 'luz', 'banos'],
                'notes' => 'Refugio comunitario con acceso principal amplio y servicios básicos.',
            ],
            [
                'district' => 'victor-larco-herrera',
                'name' => 'Centro Vecinal Víctor Larco',
                'address' => 'Jr. Los Cedros 120, Víctor Larco Herrera',
                'latitude' => -8.1360,
                'longitude' => -79.0504,
                'capacity' => 180,
                'current_occupancy' => 120,
                'status' => 'limited',
                'services' => ['agua', 'luz', 'primeros_auxilios'],
                'notes' => 'Punto de apoyo para familias y servicios básicos.',
            ],
            [
                'district' => 'huanchaco',
                'name' => 'Refugio Playa Dorada',
                'address' => 'Calle Las Dunas 18, Huanchaco',
                'latitude' => -8.0781,
                'longitude' => -79.1178,
                'capacity' => 160,
                'current_occupancy' => 130,
                'status' => 'almost_full',
                'services' => ['agua', 'banos', 'luz'],
                'notes' => 'Zona con aforo alto por actividades vecinales y turismo local.',
            ],
            [
                'district' => 'el-porvenir',
                'name' => 'Polideportivo El Porvenir',
                'address' => 'Av. San Martín 400, El Porvenir',
                'latitude' => -8.0860,
                'longitude' => -79.0048,
                'capacity' => 250,
                'current_occupancy' => 210,
                'status' => 'saturated',
                'services' => ['agua', 'luz', 'banos', 'primeros_auxilios'],
                'notes' => 'Zona con alta ocupación por alertas vecinales recientes.',
            ],
            [
                'district' => 'la-esperanza',
                'name' => 'Centro Educativo La Esperanza',
                'address' => 'Calle Las Flores 72, La Esperanza',
                'latitude' => -8.0712,
                'longitude' => -79.0439,
                'capacity' => 190,
                'current_occupancy' => 88,
                'status' => 'available',
                'services' => ['agua', 'luz', 'banos'],
                'notes' => 'Espacio con capacidad y resguardo para familias.',
            ],
            [
                'district' => 'florencia-de-mora',
                'name' => 'Gimnasio Florencia de Mora',
                'address' => 'Jr. Primavera 210, Florencia de Mora',
                'latitude' => -8.0861,
                'longitude' => -79.0269,
                'capacity' => 170,
                'current_occupancy' => 155,
                'status' => 'limited',
                'services' => ['luz', 'banos', 'primeros_auxilios'],
                'notes' => 'Uso compartido con actividades deportivas y zonas de espera.',
            ],
            [
                'district' => 'moche',
                'name' => 'Centro Cultural Moche',
                'address' => 'Calle Los Próceres 145, Moche',
                'latitude' => -8.1715,
                'longitude' => -79.0111,
                'capacity' => 140,
                'current_occupancy' => 48,
                'status' => 'available',
                'services' => ['agua', 'luz', 'banos'],
                'notes' => 'Refugio moderado con buen acceso para familias.',
            ],
            [
                'district' => 'laredo',
                'name' => 'Parque de Laredo',
                'address' => 'Parque Central 12, Laredo',
                'latitude' => -8.0894,
                'longitude' => -78.9617,
                'capacity' => 120,
                'current_occupancy' => 72,
                'status' => 'available',
                'services' => ['agua', 'luz'],
                'notes' => 'Zona segura con acceso amplio y vigilancia local.',
            ],
        ];

        foreach ($zones as $zone) {
            $district = $districts->get($zone['district']);

            if (! $district) {
                continue;
            }

            SafeZone::updateOrCreate(
                [
                    'district_id' => $district->id,
                    'name' => $zone['name'],
                ],
                [
                    'address' => $zone['address'],
                    'latitude' => $zone['latitude'],
                    'longitude' => $zone['longitude'],
                    'capacity' => $zone['capacity'],
                    'current_occupancy' => $zone['current_occupancy'],
                    'status' => $zone['status'],
                    'services' => $zone['services'],
                    'image_path' => null,
                    'notes' => $zone['notes'],
                    'is_verified' => true,
                ]
            );
        }
    }
}
