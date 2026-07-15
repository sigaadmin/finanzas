<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueActivityAssignment>
 */
class OwnRevenueActivityAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_file_id' => OwnRevenueImportFile::factory()->state([
                'format' => OwnRevenueImportFormat::Fuel,
                'detected_format' => OwnRevenueImportFormat::Fuel,
            ]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()
                ->findOrFail($attributes['own_revenue_import_file_id'])->own_revenue_budget_id,
            'own_revenue_activity_rule_id' => null,
            'assignable_type' => OwnRevenueFuelPlan::class,
            'assignable_id' => fn (array $attributes): int => OwnRevenueFuelPlan::factory()->create([
                'own_revenue_import_file_id' => $attributes['own_revenue_import_file_id'],
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'previous_activity_id' => null,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'activity_code' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->code,
            'activity_name' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->name,
            'mode' => OwnRevenueActivityAssignmentMode::AutomaticRule,
            'group_key' => 'reason:commission',
            'group_hash' => fake()->sha256(),
            'justification' => OwnRevenueActivityJustification::DescriptionClassification,
            'justification_note' => null,
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
