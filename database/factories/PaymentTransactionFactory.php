<?php

namespace Database\Factories;

use App\Enums\Finance\PaymentTransactionStatus;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_procedure_id' => PaymentProcedure::factory(),
            'registered_by' => User::factory(),
            'folio' => fake()->unique()->bothify('TX-######'),
            'status' => PaymentTransactionStatus::Paid,
            'total_pesos' => fake()->numberBetween(5000, 50000),
            'payment_method' => 'cash',
            'reference' => null,
            'paid_at' => now(),
        ];
    }
}
