<?php

namespace Database\Factories\Finance\OwnRevenue\Imports;

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueFuelPlan;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueFuelPlan>
 */
class OwnRevenueFuelPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_import_file_id' => OwnRevenueImportFile::factory()->state(['format' => OwnRevenueImportFormat::Fuel]),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueImportFile::query()->findOrFail($attributes['own_revenue_import_file_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => null,
            'source_row_id' => fn (array $attributes): int => OwnRevenueImportRow::factory()->create([
                'own_revenue_import_file_id' => $attributes['own_revenue_import_file_id'],
                'row_kind' => 'fuel_line',
            ])->id,
            'commission_date_label' => 'ABRIL',
            'month' => 4,
            'reason' => fake()->sentence(),
            'vehicle_model' => 'PARTICULAR',
            'kilometers_per_liter' => '10',
            'outbound_origin' => 'Felipe Carrillo Puerto',
            'outbound_destination' => 'Chetumal',
            'outbound_kilometers' => '150',
            'return_origin' => 'Chetumal',
            'return_destination' => 'Felipe Carrillo Puerto',
            'return_kilometers' => '150',
            'liters' => '30',
            'fuel_price' => '24.03',
            'amount_cents' => 79_300,
            'sort_order' => 0,
        ];
    }
}
