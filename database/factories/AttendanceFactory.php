<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
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
            'section_id' => Section::factory(),
            'type' => fake()->randomElement(['entrada', 'salida']),
            'occurred_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
