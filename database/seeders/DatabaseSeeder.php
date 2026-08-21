<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $empleadoRole = Role::findOrCreate('empleado');
        $adminRole = Role::findOrCreate('administrador');
        $prestadorRole = Role::findOrCreate('prestador');
        $tesoreriaRole = Role::findOrCreate('administrativo-tesoreria');
        $directorRole = Role::findOrCreate('director');

        // Create default sections
        Section::create([
            'name' => 'Recepción',
            'uuid' => (string) Str::uuid(),
        ]);
        Section::create([
            'name' => 'Quirófano',
            'uuid' => (string) Str::uuid(),
        ]);
        Section::create([
            'name' => 'Consultorios',
            'uuid' => (string) Str::uuid(),
        ]);

        // Create 4 empleados with test passwords
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
