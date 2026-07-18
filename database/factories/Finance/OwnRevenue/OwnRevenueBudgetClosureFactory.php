<?php

namespace Database\Factories\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueBudgetClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueBudgetClosure>
 */
class OwnRevenueBudgetClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory()->state([
                'status' => OwnRevenueBudgetStatus::Closed,
            ]),
            'note' => 'Cierre anual conciliado y autorizado.',
            'snapshot' => [
                'schema_version' => 1,
                'balances' => ['available_amount_cents' => '0'],
            ],
            'fingerprint' => str_repeat('c', 64),
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ];
    }
}
