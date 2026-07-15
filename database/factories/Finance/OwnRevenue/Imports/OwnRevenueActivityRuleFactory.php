<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
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
            'own_revenue_activity_id' => OwnRevenueActivity::factory(),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->own_revenue_budget_id,
            'format' => OwnRevenueImportFormat::TechnicalSheet,
            'group_key' => 'specific_item_code:21101',
            'group_hash' => function (array $attributes): string {
                $format = $attributes['format'] instanceof OwnRevenueImportFormat
                    ? $attributes['format']
                    : OwnRevenueImportFormat::from($attributes['format']);

                return hash('sha256', $format->value.'|'.$attributes['group_key']);
            },
            'group_payload' => ['specific_item_code' => '21101'],
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
