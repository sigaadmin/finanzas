<?php

namespace Database\Factories;

use App\Models\StudentSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSnapshot>
 */
class StudentSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'siga_student_id' => fake()->unique()->bothify('SIGA-####'),
            'matricula' => fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'program' => 'Licenciatura en Educacion Normal',
            'grade' => (string) fake()->numberBetween(1, 8),
            'group' => fake()->randomElement(['A', 'B', 'C']),
            'academic_status' => 'active',
        ];
    }
}
