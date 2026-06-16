<?php

namespace Database\Factories;

use App\Enums\Finance\ReceiptStatus;
use App\Enums\Finance\ReceiptType;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_transaction_id' => PaymentTransaction::factory(),
            'payment_procedure_id' => PaymentProcedure::factory(),
            'payment_procedure_item_id' => null,
            'folio' => fake()->unique()->bothify('REC-######'),
            'type' => ReceiptType::Internal,
            'status' => ReceiptStatus::Issued,
            'total_pesos' => fake()->numberBetween(5000, 50000),
            'amount_in_words' => 'CIEN PESOS 00/100 M.N.',
            'validation_token' => Str::random(48),
            'issued_at' => now(),
            'cancelled_at' => null,
        ];
    }
}
