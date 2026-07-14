<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportDecision;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueImportDecision>
 */
class OwnRevenueImportDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_issue_id' => OwnRevenueImportIssue::factory(),
            'own_revenue_import_row_id' => null,
            'current_value' => ['value' => '2027'],
            'proposed_value' => ['value' => '2026'],
            'resolved_value' => ['value' => '2027'],
            'resolution' => 'manual',
            'justification' => null,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
        ];
    }
}
