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
        // Define Spatie roles
        $empleadoRole = Role::findOrCreate('empleado');
        $adminRole = Role::findOrCreate('administrador');
        $prestadorRole = Role::findOrCreate('prestador');
        $tesoreriaRole = Role::findOrCreate('administrativo-tesoreria');
        $directorRole = Role::findOrCreate('director');

        // Create 4 employees
        for ($i = 1; $i <= 4; $i++) {
            $user = User::create([
                'name' => "Empleado {$i}",
                'email' => "empleado{$i}@example.com",
                'password' => Hash::make("empleado{$i}"),
            ]);
            $user->assignRole($empleadoRole);
        }

        // Create default admin user
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => Hash::make('administrador'),
        ]);
        $admin->assignRole($adminRole);

        // Create 2 prestadores
        for ($i = 1; $i <= 2; $i++) {
            $prestador = User::create([
                'name' => "Prestador {$i}",
                'email' => "prestador{$i}@example.com",
                'password' => Hash::make("prestador{$i}"),
            ]);
            $prestador->assignRole($prestadorRole);
        }

        // Create 1 administrativo-tesoreria
        $tesoreria = User::create([
            'name' => 'Tesoreria User',
            'email' => 'tesoreria@example.com',
            'password' => Hash::make('tesoreria'),
        ]);
        $tesoreria->assignRole($tesoreriaRole);

        // Create 1 director
        $director = User::create([
            'name' => 'Director User',
            'email' => 'director@example.com',
            'password' => Hash::make('director'),
        ]);
        $director->assignRole($directorRole);
    }
}
