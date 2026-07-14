<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportOrigin;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueImportOrigin>
 */
class OwnRevenueImportOriginFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'originable_type' => OwnRevenueAbpreLine::class,
            'originable_id' => OwnRevenueAbpreLine::factory(),
            'own_revenue_import_row_id' => OwnRevenueImportRow::factory(),
            'field_name' => null,
        ];
    }
}
