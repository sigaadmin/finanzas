<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalCut;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Services\Finance\OwnRevenue\Reports\OwnRevenuePlanningComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('planning comparison describes proposal versions UMA values and distributed cuts', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $first = OwnRevenueProposal::factory()->for($budget, 'budget')->create([
        'version_number' => 1,
        'status' => OwnRevenueProposalStatus::Calculated,
        'total_amount_cents' => 200_000,
        'calculated_at' => '2026-02-10 12:00:00',
    ]);
    OwnRevenueProposalTravelCommission::factory()->for($first, 'proposal')->create([
        'own_revenue_budget_id' => $budget->id,
        'uma_value' => '117.3100',
    ]);
    OwnRevenueProposalTravelCommission::factory()->for($first, 'proposal')->create([
        'own_revenue_budget_id' => $budget->id,
        'uma_value' => '120.0000',
    ]);
    OwnRevenueProposalCut::factory()->for($first, 'proposal')->create([
        'amount_cents' => 25_000,
    ]);
    $adjusted = OwnRevenueProposal::factory()->for($budget, 'budget')->for($first, 'basedOnProposal')->create([
        'version_number' => 2,
        'status' => OwnRevenueProposalStatus::Adjusted,
        'total_amount_cents' => 175_000,
        'calculated_at' => '2026-02-12 12:00:00',
    ]);
    OwnRevenueProposalTravelCommission::factory()->for($adjusted, 'proposal')->create([
        'own_revenue_budget_id' => $budget->id,
        'uma_value' => '117.3100',
    ]);
    $draft = OwnRevenueProposal::factory()->for($budget, 'budget')->for($adjusted, 'basedOnProposal')->create([
        'version_number' => 3,
        'status' => OwnRevenueProposalStatus::Draft,
        'total_amount_cents' => 175_000,
    ]);
    OwnRevenueProposal::factory()->create([
        'version_number' => 4,
        'total_amount_cents' => 999_999,
    ]);

    $comparison = app(OwnRevenuePlanningComparison::class)->forBudget($budget);

    expect($comparison['version_count'])->toBe(3)
        ->and($comparison['distributed_cut_amount_cents'])->toBe('25000')
        ->and($comparison['versions'])->toMatchArray([
            [
                'id' => $first->id,
                'version_number' => 1,
                'status' => 'calculated',
                'based_on_version_number' => null,
                'total_amount_cents' => '200000',
                'difference_from_previous_cents' => null,
                'applied_cut_amount_cents' => '0',
                'uma_values' => ['117.3100', '120.0000'],
                'has_mixed_uma' => true,
                'calculated_at' => '2026-02-10T12:00:00.000000Z',
            ],
            [
                'id' => $adjusted->id,
                'version_number' => 2,
                'status' => 'adjusted',
                'based_on_version_number' => 1,
                'total_amount_cents' => '175000',
                'difference_from_previous_cents' => '-25000',
                'applied_cut_amount_cents' => '25000',
                'uma_values' => ['117.3100'],
                'has_mixed_uma' => false,
                'calculated_at' => '2026-02-12T12:00:00.000000Z',
            ],
            [
                'id' => $draft->id,
                'version_number' => 3,
                'status' => 'draft',
                'based_on_version_number' => 2,
                'total_amount_cents' => '175000',
                'difference_from_previous_cents' => '0',
                'applied_cut_amount_cents' => '0',
                'uma_values' => [],
                'has_mixed_uma' => false,
                'calculated_at' => null,
            ],
        ]);
});

test('planning comparison reconciles the authorized initial budget without losing integer precision', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->create([
        'version_number' => 7,
        'status' => OwnRevenueProposalStatus::Adjusted,
        'total_amount_cents' => '9007199254740993',
    ]);
    OwnRevenueInitialBudget::factory()->for($budget, 'budget')->for($proposal, 'proposal')->create([
        'total_amount_cents' => '9007199254740900',
        'snapshot' => ['budget' => ['uma_value' => '117.3100']],
        'authorized_at' => '2026-02-15 12:00:00',
    ]);

    $comparison = app(OwnRevenuePlanningComparison::class)->forBudget($budget);

    expect($comparison['initial_authorization'])->toMatchArray([
        'proposal_version_number' => 7,
        'proposal_total_amount_cents' => '9007199254740993',
        'authorized_total_amount_cents' => '9007199254740900',
        'difference_amount_cents' => '-93',
        'uma_value' => '117.3100',
    ]);
});

test('planning comparison returns operational empty states when planning is unavailable', function () {
    $budget = OwnRevenueBudget::factory()->create();

    $comparison = app(OwnRevenuePlanningComparison::class)->forBudget($budget);

    expect($comparison)->toBe([
        'version_count' => 0,
        'distributed_cut_amount_cents' => '0',
        'versions' => [],
        'initial_authorization' => null,
    ]);
});
