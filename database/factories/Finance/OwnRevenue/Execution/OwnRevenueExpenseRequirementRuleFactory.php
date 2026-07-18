<?php

namespace Database\Factories\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirementRule;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueExpenseRequirementRule>
 */
class OwnRevenueExpenseRequirementRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'target_status' => OwnRevenueExpenseDossierStatus::PaymentRequested,
            'purchase_responsibility' => null,
            'chapter_code' => null,
            'specific_item_code' => null,
            'minimum_amount_cents' => null,
            'requires_evidence' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
