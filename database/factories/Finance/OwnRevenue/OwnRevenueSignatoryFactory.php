<?php

namespace Database\Factories\Finance\OwnRevenue;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueSignatory>
 */
class OwnRevenueSignatoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'role_key' => 'prepared_by',
            'name' => fake()->name(),
            'position' => 'Responsable de Recursos Financieros',
            'academic_degree' => fake()->randomElement(['C.P.', 'L.A.E.', null]),
            'sort_order' => 1,
        ];
    }
}
