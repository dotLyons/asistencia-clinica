<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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
    }
}
