<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueTechnicalSheetNeed>
 */
class OwnRevenueTechnicalSheetNeedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_file_id' => OwnRevenueImportFile::factory()->state(['format' => OwnRevenueImportFormat::TechnicalSheet]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()->findOrFail($attributes['own_revenue_import_file_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => null,
            'source_row_id' => fn (array $attributes): int => OwnRevenueImportRow::factory()->create([
                'own_revenue_import_file_id' => $attributes['own_revenue_import_file_id'],
                'row_kind' => 'technical_sheet_line',
            ])->id,
            'expense_classification_id' => fn (array $attributes): int => ExpenseClassification::factory()->create([
                'fiscal_year' => OwnRevenueImportFile::query()->findOrFail($attributes['own_revenue_import_file_id'])->budget->fiscal_year,
                'specific_item_code' => '21101',
                'specific_item_name' => 'Materiales de oficina',
                'chapter_code' => '2000',
                'chapter_name' => 'Materiales y suministros',
            ])->id,
            'specific_item_code' => '21101',
            'specific_item_name' => 'Materiales de oficina',
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y suministros',
            'sequence' => '1',
            'quantity' => '2',
            'unit' => 'Pieza',
            'description' => fake()->sentence(),
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'amount_cents' => fake()->numberBetween(100, 100_000),
            'budget_month' => 4,
            'sort_order' => 0,
        ];
    }
}
