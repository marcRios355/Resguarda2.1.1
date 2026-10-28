<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DistrictSeeder::class,
            SafeZoneSeeder::class,
        ]);

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'last_name' => 'RESGUARDA',
                'email' => 'admin@resguarda.pe',
                'role' => 'admin',
                'reliability_level' => 'reliable',
                'reputation_score' => 100,
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['username' => 'VictorResguarda'],
            [
                'name' => 'Víctor',
                'last_name' => 'Ciudadano',
                'email' => 'victor@resguarda.pe',
                'role' => 'user',
                'reliability_level' => 'reliable',
                'reputation_score' => 80,
                'status' => 'active',
            ]
        );
    }
}
