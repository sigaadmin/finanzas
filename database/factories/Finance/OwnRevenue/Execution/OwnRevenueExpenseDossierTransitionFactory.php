<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueExpenseDossierTransition>
 */
class OwnRevenueExpenseDossierTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_expense_dossier_id' => OwnRevenueExpenseDossier::factory(),
            'from_status' => null,
            'to_status' => OwnRevenueExpenseDossierStatus::Draft,
            'reason' => null,
            'actor_id' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
