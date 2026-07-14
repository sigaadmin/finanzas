<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueWorkSheetLine>
 */
class OwnRevenueWorkSheetLineFactory extends Factory
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
                'format' => OwnRevenueImportFormat::WorkSheet,
                'detected_format' => OwnRevenueImportFormat::WorkSheet,
                'original_name' => 'Hoja de trabajo.xlsx',
            ]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()
                ->findOrFail($attributes['own_revenue_import_file_id'])
                ->own_revenue_budget_id,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
            ])->id,
            'expense_classification_id' => fn (array $attributes): int => ExpenseClassification::factory()->create([
                'fiscal_year' => OwnRevenueImportFile::query()
                    ->findOrFail($attributes['own_revenue_import_file_id'])
                    ->budget
                    ->fiscal_year,
                'chapter_code' => '2000',
                'chapter_name' => 'Materiales y suministros',
                'concept_code' => '2100',
                'concept_name' => 'Materiales de administración',
                'generic_item_code' => '21100',
                'generic_item_name' => 'Materiales y útiles de oficina',
                'specific_item_code' => '21101',
                'specific_item_name' => 'Materiales y útiles de oficina',
                'expense_type_code' => '1',
                'expense_type_name' => 'Gasto corriente',
            ])->id,
            'activity_code' => 'A03-A01',
            'activity_name' => 'Servicios escolares',
            'item_name' => 'Materiales y útiles de oficina',
            'specific_item_code' => '21101',
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'annual_amount_cents' => fake()->numberBetween(10_000, 1_000_000),
            'sort_order' => 0,
        ];
    }
}
