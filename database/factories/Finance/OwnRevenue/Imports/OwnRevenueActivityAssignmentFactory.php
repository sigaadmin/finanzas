<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignmentMode;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueActivityJustification;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityAssignment;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueActivityRule;
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
            'own_revenue_activity_rule_id' => OwnRevenueActivityRule::factory()->state([
                'format' => OwnRevenueImportFormat::Fuel,
                'group_key' => 'reason:commission',
                'group_payload' => ['reason' => 'commission'],
            ]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueActivityRule::query()
                ->findOrFail($attributes['own_revenue_activity_rule_id'])->own_revenue_budget_id,
            'own_revenue_import_file_id' => function (array $attributes): int {
                $recycledFile = $this->getRandomRecycledModel(OwnRevenueImportFile::class);

                if ($recycledFile !== null) {
                    return $recycledFile->getKey();
                }

                $rule = OwnRevenueActivityRule::query()->findOrFail($attributes['own_revenue_activity_rule_id']);

                return OwnRevenueImportFile::factory()
                    ->recycle($rule->budget)
                    ->state([
                        'format' => $rule->format,
                        'detected_format' => $rule->format,
                    ])
                    ->create()
                    ->getKey();
            },
            'assignable_type' => OwnRevenueFuelPlan::class,
            'assignable_id' => fn (array $attributes): int => OwnRevenueFuelPlan::factory()->create([
                'own_revenue_import_file_id' => $attributes['own_revenue_import_file_id'],
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'previous_activity_id' => null,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivityRule::query()
                ->findOrFail($attributes['own_revenue_activity_rule_id'])->own_revenue_activity_id,
            'activity_code' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->code,
            'activity_name' => fn (array $attributes): string => OwnRevenueActivity::query()
                ->findOrFail($attributes['own_revenue_activity_id'])->name,
            'mode' => OwnRevenueActivityAssignmentMode::GroupRule,
            'group_key' => fn (array $attributes): string => OwnRevenueActivityRule::query()
                ->findOrFail($attributes['own_revenue_activity_rule_id'])->group_key,
            'group_hash' => fn (array $attributes): string => OwnRevenueActivityRule::query()
                ->findOrFail($attributes['own_revenue_activity_rule_id'])->group_hash,
            'justification' => fn (array $attributes): OwnRevenueActivityJustification => OwnRevenueActivityRule::query()
                ->findOrFail($attributes['own_revenue_activity_rule_id'])->justification,
            'justification_note' => null,
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}
