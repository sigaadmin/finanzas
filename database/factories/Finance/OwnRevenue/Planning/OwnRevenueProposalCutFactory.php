<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposalCut> */
class OwnRevenueProposalCutFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_proposal_id' => OwnRevenueProposal::factory(),
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create([
                'own_revenue_budget_id' => OwnRevenueProposal::query()
                    ->findOrFail($attributes['own_revenue_proposal_id'])->own_revenue_budget_id,
            ])->id,
            'target_type' => 'technical',
            'target_id' => 1,
            'stable_key' => fake()->uuid(),
            'specific_item_code' => '21101',
            'budget_month' => 4,
            'available_amount_cents' => 1000,
            'amount_cents' => 100,
            'created_by' => User::factory(),
        ];
    }
}
