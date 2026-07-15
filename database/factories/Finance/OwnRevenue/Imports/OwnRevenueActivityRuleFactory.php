<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueActivityRule>
 */
class OwnRevenueActivityRuleFactory extends Factory
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
            'format' => OwnRevenueImportFormat::TechnicalSheet,
            'group_key' => 'specific_item_code:21101',
            'group_hash' => fake()->sha256(),
            'group_payload' => ['specific_item_code' => '21101'],
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'activity_code' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->code,
            'activity_name' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->name,
            'justification' => OwnRevenueActivityJustification::DescriptionClassification,
            'justification_note' => null,
            'created_by' => User::factory(),
            'is_active' => true,
            'deactivated_by' => null,
            'deactivated_at' => null,
            'replaces_rule_id' => null,
        ];
    }
}
