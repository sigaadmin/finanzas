<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueBudgetModification>
 */
class OwnRevenueBudgetModificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_line_id' => OwnRevenueModifiedBudgetLine::factory(),
            'own_revenue_budget_id' => fn (array $attributes) => OwnRevenueModifiedBudgetLine::query()
                ->findOrFail($attributes['source_line_id'])->own_revenue_budget_id,
            'destination_line_id' => fn (array $attributes) => OwnRevenueModifiedBudgetLine::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
                'own_revenue_initial_budget_id' => OwnRevenueModifiedBudgetLine::query()
                    ->findOrFail($attributes['source_line_id'])->own_revenue_initial_budget_id,
                'expense_classification_id' => OwnRevenueModifiedBudgetLine::query()
                    ->findOrFail($attributes['source_line_id'])->expense_classification_id,
                'month' => 2,
                'initial_amount_cents' => 0,
            ])->id,
            'type' => OwnRevenueBudgetModificationType::Rescheduling,
            'amount_cents' => 1_000,
            'reason' => 'La compra se realizará durante el mes siguiente.',
            'source_balance_before_cents' => 10_000,
            'source_balance_after_cents' => 9_000,
            'destination_balance_before_cents' => 0,
            'destination_balance_after_cents' => 1_000,
            'recorded_by' => User::factory(),
            'recorded_at' => now(),
        ];
    }
}
