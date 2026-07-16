<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OwnRevenueProposal> */
class OwnRevenueProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory(),
            'version_number' => 1,
            'status' => OwnRevenueProposalStatus::Draft,
            'based_on_proposal_id' => null,
            'source_abpre_file_id' => null,
            'source_work_sheet_file_id' => null,
            'source_technical_sheet_file_id' => null,
            'source_fuel_file_id' => null,
            'source_travel_expenses_file_id' => null,
            'source_fingerprint' => null,
            'total_amount_cents' => 0,
            'created_by' => User::factory(),
            'calculated_by' => null,
            'calculated_at' => null,
        ];
    }
}
