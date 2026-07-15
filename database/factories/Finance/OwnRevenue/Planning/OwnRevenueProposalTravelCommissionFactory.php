<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposalTravelCommission> */
class OwnRevenueProposalTravelCommissionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_proposal_id' => OwnRevenueProposal::factory(),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueProposal::query()->findOrFail($attributes['own_revenue_proposal_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create(['own_revenue_budget_id' => $attributes['own_revenue_budget_id']])->id,
            'source_travel_commission_id' => null,
            'own_revenue_travel_destination_id' => null,
            'stable_key' => fake()->uuid(),
            'commission_date_label' => 'ABRIL',
            'operational_month' => 4,
            'budget_month' => 4,
            'reason' => fake()->sentence(),
            'destination' => 'Chetumal',
            'food_zone' => 1,
            'lodging_zone' => 1,
            'uma_value' => '117.3100',
            'flight_amount_cents' => 0,
            'participants_amount_cents' => 18_770,
            'total_amount_cents' => 18_770,
            'override_justification' => null,
            'sort_order' => 0,
        ];
    }
}
