<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueImportRow>
 */
class OwnRevenueImportRowFactory extends Factory
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
            'sheet_name' => 'ABRPRE-01',
            'row_number' => fake()->unique()->numberBetween(1, 1000),
            'row_kind' => 'budget_line',
            'row_hash' => fake()->unique()->sha256(),
            'source_payload' => ['Partida' => '21101'],
            'normalized_payload' => ['specific_item_code' => '21101'],
        ];
    }
}
