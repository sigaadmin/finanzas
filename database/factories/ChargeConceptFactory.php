<?php

namespace Database\Factories;

use App\Enums\Finance\ChargeConceptStatus;
use App\Enums\Finance\ChargeConceptType;
use App\Models\ChargeConcept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeConcept>
 */
class ChargeConceptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'amount_pesos' => fake()->numberBetween(5000, 50000),
            'type' => fake()->randomElement(ChargeConceptType::cases()),
            'allows_quantity' => false,
            'status' => ChargeConceptStatus::Active,
            'internal_key' => fake()->optional()->bothify('CON-###'),
            'valid_from' => null,
            'valid_until' => null,
        ];
    }
}
