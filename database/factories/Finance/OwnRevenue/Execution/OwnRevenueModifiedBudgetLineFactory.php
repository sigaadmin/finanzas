<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueModifiedBudgetLine>
 */
class OwnRevenueModifiedBudgetLineFactory extends Factory
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
                'status' => OwnRevenueBudgetStatus::InitialAuthorized,
            ]),
            'own_revenue_initial_budget_id' => fn (array $attributes) => OwnRevenueInitialBudget::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'expense_classification_id' => function (array $attributes): int {
                $budget = OwnRevenueBudget::query()->findOrFail($attributes['own_revenue_budget_id']);

                return ExpenseClassification::query()->create([
                    'fiscal_year' => $budget->fiscal_year,
                    'chapter_code' => '2000',
                    'chapter_name' => 'Materiales y suministros',
                    'concept_code' => '21000',
                    'concept_name' => 'Materiales de administración',
                    'generic_item_code' => '21100',
                    'generic_item_name' => 'Materiales y útiles de oficina',
                    'specific_item_code' => '21101',
                    'specific_item_name' => 'Materiales y útiles de oficina',
                    'expense_type_code' => '1',
                    'expense_type_name' => 'Gasto corriente',
                ])->id;
            },
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y suministros',
            'specific_item_code' => '21101',
            'specific_item_name' => 'Materiales y útiles de oficina',
            'month' => 1,
            'initial_amount_cents' => 10_000,
        ];
    }
}
