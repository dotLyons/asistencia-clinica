<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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

        // Create 4 empleados with test passwords
        for ($i = 1; $i <= 4; $i++) {
            $user = User::factory()->create([
                'name' => "Empleado {$i}",
                'email' => "empleado{$i}@example.com",
                'password' => Hash::make("empleado{$i}"),
            ]);
            $user->assignRole($empleadoRole);
        }

        // Create default admin user
        $admin = User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => Hash::make('administrador'),
        ]);
        $admin->assignRole($adminRole);
    }
}
