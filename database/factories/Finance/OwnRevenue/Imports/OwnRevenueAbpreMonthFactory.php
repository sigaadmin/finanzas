<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreMonth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueAbpreMonth>
 */
class OwnRevenueAbpreMonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_abpre_line_id' => OwnRevenueAbpreLine::factory(),
            'month' => fake()->numberBetween(1, 12),
            'amount_cents' => fake()->numberBetween(0, 100_000),
        ];
    }
}
