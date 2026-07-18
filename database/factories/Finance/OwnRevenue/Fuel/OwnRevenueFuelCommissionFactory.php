<?php

namespace Database\Factories\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelCommission;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueFuelCommission>
 */
class OwnRevenueFuelCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_fuel_fund_id' => OwnRevenueFuelFund::factory(),
            'own_revenue_proposal_fuel_need_id' => null,
            'status' => OwnRevenueFuelCommissionStatus::Pending,
            'commission_date' => '2026-05-15',
            'reason' => fake()->sentence(),
            'route_description' => 'Felipe Carrillo Puerto - Cancún - Felipe Carrillo Puerto',
            'vehicle_description' => 'Nissan NP300',
            'kilometers' => '220.0000',
            'liters' => '10.0000',
            'amount_cents' => 25_000,
            'effective_price_per_liter' => '25.0000',
            'is_extraordinary' => false,
            'extraordinary_justification' => null,
            'balance_after_cents' => null,
            'created_by' => User::factory(),
            'confirmed_by' => null,
            'confirmed_at' => null,
        ];
    }
}
