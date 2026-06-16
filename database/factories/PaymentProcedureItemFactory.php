<?php

namespace Database\Factories;

use App\Enums\Finance\ChargeConceptType;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use App\Models\PaymentProcedureItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentProcedureItem>
 */
class PaymentProcedureItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amountPesos = fake()->numberBetween(5000, 50000);

        return [
            'payment_procedure_id' => PaymentProcedure::factory(),
            'charge_concept_id' => ChargeConcept::factory(),
            'concept_name' => fake()->sentence(4),
            'concept_type' => ChargeConceptType::Internal,
            'unit_amount_pesos' => $amountPesos,
            'quantity' => 1,
            'subtotal_pesos' => $amountPesos,
        ];
    }
}
