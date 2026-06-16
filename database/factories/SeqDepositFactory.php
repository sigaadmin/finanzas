<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\SeqDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeqDeposit>
 */
class SeqDepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'registered_by' => User::factory(),
            'deposit_date' => fake()->date(),
            'bank_transaction_folio' => fake()->bothify('BBVA-######'),
            'deposit_type' => fake()->randomElement(['ventanilla', 'practicaja', 'transferencia']),
            'deposit_concept' => fake()->sentence(3),
            'amount_pesos' => fake()->numberBetween(500, 5000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
