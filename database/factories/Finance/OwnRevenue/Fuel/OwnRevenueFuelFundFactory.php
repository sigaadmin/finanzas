<?php

namespace Database\Factories\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueFuelFund>
 */
class OwnRevenueFuelFundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_expense_dossier_id' => function (): int {
                $line = OwnRevenueModifiedBudgetLine::factory()->create([
                    'specific_item_code' => '26101',
                    'specific_item_name' => 'Combustibles, lubricantes y aditivos',
                    'month' => 4,
                ]);

                return OwnRevenueExpenseDossier::factory()->create([
                    'own_revenue_budget_id' => $line->own_revenue_budget_id,
                    'own_revenue_modified_budget_line_id' => $line->id,
                    'status' => OwnRevenueExpenseDossierStatus::Paid,
                    'paid_by' => User::factory(),
                    'paid_at' => now(),
                ])->id;
            },
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueExpenseDossier::query()
                ->findOrFail($attributes['source_expense_dossier_id'])
                ->own_revenue_budget_id,
            'acquired_amount_cents' => fake()->numberBetween(50_000, 500_000),
            'opened_by' => User::factory(),
            'opened_at' => now(),
        ];
    }
}
