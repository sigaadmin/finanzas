<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueAbpreLine>
 */
class OwnRevenueAbpreLineFactory extends Factory
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
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()
                ->findOrFail($attributes['own_revenue_import_file_id'])
                ->own_revenue_budget_id,
            'expense_classification_id' => ExpenseClassification::factory()->state([
                'fiscal_year' => 2027,
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
            ]),
            'responsible_unit_code' => '2112102003',
            'responsible_unit_name' => 'Dirección del Plantel',
            'budget_program_code' => 'E062',
            'budget_program_name' => 'Formación Inicial Docente',
            'component_code' => 'C01',
            'component_name' => 'Servicios de formación docente proporcionados',
            'official_activity_code' => 'A01',
            'official_activity_name' => 'Operación de los programas de formación docente',
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'specific_expense_concept_code' => null,
            'specific_item_code' => '21101',
            'annual_amount_cents' => fake()->numberBetween(10_000, 1_000_000),
            'sort_order' => 0,
        ];
    }
}
