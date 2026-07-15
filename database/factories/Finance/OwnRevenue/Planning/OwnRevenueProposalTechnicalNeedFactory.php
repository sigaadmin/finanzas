<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposalTechnicalNeed> */
class OwnRevenueProposalTechnicalNeedFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_proposal_id' => OwnRevenueProposal::factory(),
            'own_revenue_budget_id' => fn (array $attributes): int => OwnRevenueProposal::query()->findOrFail($attributes['own_revenue_proposal_id'])->own_revenue_budget_id,
            'own_revenue_activity_id' => fn (array $attributes): int => OwnRevenueActivity::factory()->create(['own_revenue_budget_id' => $attributes['own_revenue_budget_id']])->id,
            'source_technical_sheet_need_id' => null,
            'expense_classification_id' => null,
            'stable_key' => fake()->uuid(),
            'specific_item_code' => '21101',
            'specific_item_name' => 'Materiales y útiles de oficina',
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y suministros',
            'sequence' => '1',
            'quantity' => '2.5000',
            'unit' => 'PIEZA',
            'description' => fake()->sentence(),
            'unit_price_cents' => 10_025,
            'reference_amount_cents' => 25_063,
            'budget_amount_cents' => 25_063,
            'budget_month' => 4,
            'impact_on_goals' => null,
            'region_code' => '02-001',
            'region_name' => 'Felipe Carrillo Puerto',
            'sort_order' => 0,
        ];
    }
}
