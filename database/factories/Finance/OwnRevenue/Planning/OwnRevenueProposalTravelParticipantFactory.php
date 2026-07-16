<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposalTravelParticipant> */
class OwnRevenueProposalTravelParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_proposal_travel_commission_id' => OwnRevenueProposalTravelCommission::factory(),
            'own_revenue_proposal_id' => fn (array $attributes): int => OwnRevenueProposalTravelCommission::query()->findOrFail($attributes['own_revenue_proposal_travel_commission_id'])->own_revenue_proposal_id,
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueProposalTravelCommission::query()->findOrFail($attributes['own_revenue_proposal_travel_commission_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueProposalTravelCommission::query()->findOrFail($attributes['own_revenue_proposal_travel_commission_id'])->own_revenue_activity_id,
            'source_travel_commission_id' => null,
            'own_revenue_travel_rate_id' => null,
            'stable_key' => fake()->uuid(),
            'person_name' => fake()->name(),
            'position' => 'DOCENTE',
            'commission_days' => '2.0000',
            'per_diem_uma' => '10.0000',
            'lodging_uma' => '8.0000',
            'per_diem_amount_cents' => 11_731,
            'lodging_amount_cents' => 7_039,
            'total_amount_cents' => 18_770,
            'sort_order' => 0,
        ];
    }
}
