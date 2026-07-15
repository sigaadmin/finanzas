<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueTravelRate> */
class OwnRevenueTravelRateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'position' => 'DOCENTE',
            'normalized_position' => 'docente',
            'food_zone' => 1,
            'lodging_zone' => 1,
            'per_diem_uma' => '10.0000',
            'lodging_uma' => '8.0000',
            'is_fallback' => false,
            'is_active' => true,
        ];
    }
}
