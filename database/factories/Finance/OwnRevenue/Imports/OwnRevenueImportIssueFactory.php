<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueImportIssue>
 */
class OwnRevenueImportIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_file_id' => OwnRevenueImportFile::factory(),
            'own_revenue_import_row_id' => null,
            'severity' => OwnRevenueImportIssueSeverity::Warning,
            'code' => 'year_mismatch',
            'field' => 'fiscal_year',
            'message' => 'El año detectado no coincide con el ejercicio.',
            'context' => ['detected_year' => 2026, 'fiscal_year' => 2027],
        ];
    }
}
