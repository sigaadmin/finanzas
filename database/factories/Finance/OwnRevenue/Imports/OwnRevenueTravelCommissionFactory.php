<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTravelCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueTravelCommission>
 */
class OwnRevenueTravelCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_file_id' => OwnRevenueImportFile::factory()->state(['format' => OwnRevenueImportFormat::TravelExpenses]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()->findOrFail($attributes['own_revenue_import_file_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => null,
            'source_row_id' => fn (array $attributes): int => OwnRevenueImportRow::factory()->create([
                'own_revenue_import_file_id' => $attributes['own_revenue_import_file_id'],
                'row_kind' => 'travel_expenses_line',
            ])->id,
            'commission_date_label' => 'ABRIL',
            'month' => 4,
            'reason' => fake()->sentence(),
            'person_name' => fake()->name(),
            'position' => 'Docente',
            'commission_days' => '2',
            'destination' => 'Chetumal',
            'per_diem_uma' => '10',
            'lodging_uma' => '8',
            'uma_value' => '117.31',
            'per_diem_amount_cents' => 117_310,
            'lodging_amount_cents' => 93_848,
            'total_amount_cents' => 211_158,
            'flight_amount_cents' => 0,
            'sort_order' => 0,
        ];
    }
}
