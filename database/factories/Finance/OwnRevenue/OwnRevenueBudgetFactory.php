<?php

namespace Database\Factories\Finance\OwnRevenue;

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueBudget>
 */
class OwnRevenueBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fiscalYear = fake()->unique()->numberBetween(2026, 2125);

        return [
            'created_by' => User::factory(),
            'fiscal_year' => $fiscalYear,
            'status' => OwnRevenueBudgetStatus::Draft,
            'institution_name' => 'Centro Regional de Educación Normal Lic. Javier Rojo Gómez',
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
            'estimated_income_cents' => fake()->numberBetween(500_000, 5_000_000),
            'cut_percentage' => '5.00',
            'uma_value' => '113.1400',
            'uma_status' => AnnualValueStatus::Final,
            'fuel_price_per_liter' => '24.5000',
            'fuel_price_status' => AnnualValueStatus::Provisional,
            'fuel_budget_month' => 4,
            'cog_source_year' => $fiscalYear,
            'cog_status' => CogCatalogStatus::PendingConfirmation,
            'cog_confirmed_by' => null,
            'cog_confirmed_at' => null,
        ];
    }
}
