<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueAbpreJustification>
 */
class OwnRevenueAbpreJustificationFactory extends Factory
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
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y suministros',
            'specific_item_code' => '21101',
            'specific_item_name' => 'Materiales y útiles de oficina',
            'budget_program_code' => 'E062',
            'budget_program_name' => 'Formación Inicial Docente',
            'component_code' => 'C01',
            'component_name' => 'Servicios de formación docente proporcionados',
            'goals_impact' => 'Permite cumplir las metas institucionales.',
            'justification' => 'Material necesario para la operación académica.',
            'sort_order' => 0,
        ];
    }
}
