<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Spatie roles for new entities
        $prestadorRole = Role::findOrCreate('prestador');
        $tesoreriaRole = Role::findOrCreate('administrativo-tesoreria');
        $directorRole = Role::findOrCreate('director');

        // Create 2 prestadores using firstOrCreate to avoid unique constraint violations
        for ($i = 1; $i <= 2; $i++) {
            $prestador = User::firstOrCreate(
                ['email' => "prestador{$i}@example.com"],
                [
                    'name' => "Prestador {$i}",
                    'password' => Hash::make("prestador{$i}"),
                ]
            );
            $prestador->assignRole($prestadorRole);
        }

        // Create 1 administrativo-tesoreria
        $tesoreria = User::firstOrCreate(
            ['email' => 'tesoreria@example.com'],
            [
                'name' => 'Tesoreria User',
                'password' => Hash::make('tesoreria'),
            ]
        );
        $tesoreria->assignRole($tesoreriaRole);

        // Create 1 director
        $director = User::firstOrCreate(
            ['email' => 'director@example.com'],
            [
                'name' => 'Director User',
                'password' => Hash::make('director'),
            ]
        );
        $director->assignRole($directorRole);
    }
}
