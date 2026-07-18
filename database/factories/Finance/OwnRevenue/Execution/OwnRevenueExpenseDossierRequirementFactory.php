<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseRequirementStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossierRequirement;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueExpenseDossierRequirement>
 */
class OwnRevenueExpenseDossierRequirementFactory extends Factory
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
            'own_revenue_expense_requirement_rule_id' => function (array $attributes): int {
                $dossier = OwnRevenueExpenseDossier::query()->findOrFail($attributes['own_revenue_expense_dossier_id']);

                return OwnRevenueExpenseRequirementRule::factory()->create([
                    'own_revenue_budget_id' => $dossier->own_revenue_budget_id,
                ])->id;
            },
            'status' => OwnRevenueExpenseRequirementStatus::Pending,
            'notes' => null,
            'evidence_document_id' => null,
            'exception_reason' => null,
            'exception_evidence_document_id' => null,
            'acted_by' => null,
            'acted_at' => null,
        ];
    }
}
