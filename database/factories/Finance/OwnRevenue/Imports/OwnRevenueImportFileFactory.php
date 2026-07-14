<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueImportFile>
 */
class OwnRevenueImportFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_session_id' => OwnRevenueImportSession::factory(),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportSession::query()
                ->findOrFail($attributes['own_revenue_import_session_id'])
                ->own_revenue_budget_id,
            'uploaded_by' => User::factory(),
            'format' => OwnRevenueImportFormat::Abpre,
            'detected_format' => OwnRevenueImportFormat::Abpre,
            'detected_year' => 2027,
            'original_name' => 'ABPRE-01.xlsx',
            'storage_disk' => 'local',
            'storage_path' => 'own-revenue/imports/'.fake()->uuid().'.xlsx',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'sha256' => fake()->unique()->sha256(),
            'version_number' => 1,
            'status' => OwnRevenueImportFileStatus::Uploaded,
            'detection_confidence' => 100,
            'detection_evidence' => [],
            'budget_updated_at_at_analysis' => null,
            'analyzed_at' => null,
            'confirmed_by' => null,
            'confirmed_at' => null,
            'replaced_by_file_id' => null,
        ];
    }
}
