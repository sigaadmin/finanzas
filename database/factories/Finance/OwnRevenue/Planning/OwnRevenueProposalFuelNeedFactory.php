<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposalFuelNeed> */
class OwnRevenueProposalFuelNeedFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_proposal_id' => OwnRevenueProposal::factory(),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueProposal::query()->findOrFail($attributes['own_revenue_proposal_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create(['own_revenue_budget_id' => $attributes['own_revenue_budget_id']])->id,
            'source_fuel_plan_id' => null,
            'own_revenue_route_id' => null,
            'stable_key' => fake()->uuid(),
            'commission_date_label' => 'ABRIL',
            'operational_month' => 4,
            'budget_month' => 4,
            'reason' => fake()->sentence(),
            'vehicle_model' => 'PARTICULAR',
            'kilometers_per_liter' => '10.0000',
            'outbound_origin' => 'Felipe Carrillo Puerto',
            'outbound_destination' => 'Chetumal',
            'outbound_kilometers' => '150.0000',
            'return_origin' => 'Chetumal',
            'return_destination' => 'Felipe Carrillo Puerto',
            'return_kilometers' => '150.0000',
            'additional_kilometers' => '0.0000',
            'total_kilometers' => '300.0000',
            'liters' => '30.0000',
            'fuel_price' => '24.5000',
            'mathematical_amount_cents' => 73_500,
            'rounded_amount_cents' => 73_500,
            'budget_amount_cents' => 75_000,
            'rounding_difference_cents' => 1_500,
            'override_justification' => null,
            'sort_order' => 0,
        ];
    }
}
