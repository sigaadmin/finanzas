<?php

namespace Database\Factories;

use App\Models\OfficialFeeConcept;
use App\Models\OfficialFeeSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficialFeeConcept>
 */
class OfficialFeeConceptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'official_fee_schedule_id' => OfficialFeeSchedule::factory(),
            'code' => fake()->unique()->numerify('##.#.#'),
            'name' => fake()->sentence(5),
            'amount_pesos' => fake()->numberBetween(5000, 150000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
