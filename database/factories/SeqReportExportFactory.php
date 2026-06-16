<?php

namespace Database\Factories;

use App\Models\SeqReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeqReportExport>
 */
class SeqReportExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generated_by' => User::factory(),
            'period_month' => now()->format('Y-m'),
            'filters' => ['period' => now()->format('Y-m')],
            'total_pesos' => fake()->numberBetween(5000, 50000),
            'receipt_count' => fake()->numberBetween(1, 10),
            'exported_at' => null,
        ];
    }
}
