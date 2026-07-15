<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OwnRevenueRoute> */
class OwnRevenueRouteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'origin' => 'Felipe Carrillo Puerto',
            'normalized_origin' => 'felipe carrillo puerto',
            'destination' => fake()->city(),
            'normalized_destination' => fn (array $attributes): string => Str::lower($attributes['destination']),
            'one_way_kilometers' => '150.0000',
            'additional_kilometers' => '0.0000',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
