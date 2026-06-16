<?php

namespace Database\Factories;

use App\Enums\Finance\OfficialFeeScheduleStatus;
use App\Models\OfficialFeeSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficialFeeSchedule>
 */
class OfficialFeeScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year' => fake()->unique()->numberBetween(2024, 2035),
            'source_name' => 'Periódico Oficial del Estado de Quintana Roo',
            'source_url' => fake()->optional()->url(),
            'published_on' => fake()->date(),
            'status' => OfficialFeeScheduleStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
