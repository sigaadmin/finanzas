<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueWorkSheetMonth>
 */
class OwnRevenueWorkSheetMonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_work_sheet_line_id' => OwnRevenueWorkSheetLine::factory(),
            'month' => fake()->numberBetween(1, 12),
            'amount_cents' => fake()->numberBetween(0, 100_000),
        ];
    }
}
