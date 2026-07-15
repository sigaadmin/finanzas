<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OwnRevenueTravelDestination> */
class OwnRevenueTravelDestinationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'destination' => fake()->city(),
            'normalized_destination' => fn (array $attributes): string => Str::lower($attributes['destination']),
            'food_zone' => 1,
            'lodging_zone' => 1,
            'is_active' => true,
        ];
    }
}
