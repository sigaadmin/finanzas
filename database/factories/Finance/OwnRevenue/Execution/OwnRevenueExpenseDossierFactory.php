<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueExpenseDossier>
 */
class OwnRevenueExpenseDossierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sequenceNumber = fake()->unique()->numberBetween(1, 9999);

        return [
            'own_revenue_modified_budget_line_id' => OwnRevenueModifiedBudgetLine::factory(),
            'own_revenue_budget_id' => function (array $attributes): int {
                return OwnRevenueModifiedBudgetLine::query()
                    ->findOrFail($attributes['own_revenue_modified_budget_line_id'])
                    ->own_revenue_budget_id;
            },
            'sequence_number' => $sequenceNumber,
            'folio' => function (array $attributes) use ($sequenceNumber): string {
                $budget = OwnRevenueBudget::query()->findOrFail($attributes['own_revenue_budget_id']);

                return sprintf('IP-%d-%04d', $budget->fiscal_year, $sequenceNumber);
            },
            'status' => OwnRevenueExpenseDossierStatus::Draft,
            'concept' => fake()->sentence(),
            'amount_cents' => fake()->numberBetween(1_000, 8_000),
            'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren,
            'external_reference' => null,
            'purchase_reference' => null,
            'payment_request_reference' => null,
            'notes' => fake()->optional()->sentence(),
            'requested_by' => User::factory(),
            'sufficiency_requested_at' => null,
            'sufficiency_confirmed_by' => null,
            'sufficiency_confirmed_at' => null,
            'purchase_started_by' => null,
            'purchase_started_at' => null,
            'payment_requested_by' => null,
            'payment_requested_at' => null,
        ];
    }
}
