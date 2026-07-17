<?php

namespace Database\Factories\Finance\OwnRevenue\Planning;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnRevenueInitialBudget>
 */
class OwnRevenueInitialBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'own_revenue_budget_id' => OwnRevenueBudget::factory()->state([
                'status' => OwnRevenueBudgetStatus::InitialAuthorized,
            ]),
            'own_revenue_proposal_id' => fn (array $attributes) => OwnRevenueProposal::factory()->create([
                'own_revenue_budget_id' => $attributes['own_revenue_budget_id'],
                'total_amount_cents' => 10_000,
            ])->id,
            'total_amount_cents' => 10_000,
            'source_fingerprint' => str_repeat('a', 64),
            'authorization_fingerprint' => str_repeat('b', 64),
            'snapshot' => [
                'reconciliation' => [
                    'groups' => [[
                        'specific_item_code' => '21101',
                        'month' => 1,
                        'target_amount_cents' => '10000',
                    ]],
                ],
            ],
            'authorized_by' => User::factory(),
            'authorized_at' => now(),
        ];
    }
}
