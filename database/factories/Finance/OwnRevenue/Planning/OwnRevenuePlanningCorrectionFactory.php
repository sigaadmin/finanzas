<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\Planning\OwnRevenuePlanningCorrection;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenuePlanningCorrection> */
class OwnRevenuePlanningCorrectionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'correctable_type' => OwnRevenueProposalTechnicalNeed::class,
            'correctable_id' => OwnRevenueProposalTechnicalNeed::factory(),
            'own_revenue_proposal_id' => fn (array $attributes): int => OwnRevenueProposalTechnicalNeed::query()->findOrFail($attributes['correctable_id'])->own_revenue_proposal_id,
            'field' => 'budget_amount_cents',
            'old_value' => '10000',
            'new_value' => '12000',
            'justification' => fake()->sentence(),
            'corrected_by' => User::factory(),
            'corrected_at' => now(),
        ];
    }
}
