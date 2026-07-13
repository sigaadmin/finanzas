<?php

namespace Database\Factories\Finance\OwnRevenue;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueActivity>
 */
class OwnRevenueActivityFactory extends Factory
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
            'code' => fake()->unique()->bothify('ACT-###'),
            'name' => fake()->randomElement([
                'Inscripción y reinscripción escolar',
                'Servicios de titulación',
                'Cursos de actualización docente',
            ]),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
