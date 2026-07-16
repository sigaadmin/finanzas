<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenuePlanningCorrection;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;
use Illuminate\Database\QueryException;

test('planning proposals persist versioned specialized needs and their audit trail', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $actor = User::factory()->create();
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($actor, 'creator')->create();

    $route = OwnRevenueRoute::factory()->for($budget, 'budget')->create();
    $destination = OwnRevenueTravelDestination::factory()->for($budget, 'budget')->create();
    $rate = OwnRevenueTravelRate::factory()->for($budget, 'budget')->create();
    $technicalNeed = OwnRevenueProposalTechnicalNeed::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['stable_key' => 'technical-need-1']);
    $fuelNeed = OwnRevenueProposalFuelNeed::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($route, 'route')
        ->create();
    $commission = OwnRevenueProposalTravelCommission::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->for($destination, 'travelDestination')
        ->create();
    $participant = OwnRevenueProposalTravelParticipant::factory()
        ->for($commission, 'commission')
        ->for($rate, 'travelRate')
        ->create();
    OwnRevenuePlanningCorrection::factory()
        ->for($proposal, 'proposal')
        ->for($technicalNeed, 'correctable')
        ->for($actor, 'actor')
        ->create([
            'field' => 'quantity',
            'old_value' => '10.0000',
            'new_value' => '12.0000',
        ]);

    expect($proposal->status)->toBe(OwnRevenueProposalStatus::Draft)
        ->and($proposal->technicalNeeds)->toHaveCount(1)
        ->and($proposal->fuelNeeds)->toHaveCount(1)
        ->and($fuelNeed->rounding_difference_cents)->toBe(1_500)
        ->and($proposal->travelCommissions->sole()->participants)->toHaveCount(1)
        ->and($participant->own_revenue_proposal_id)->toBe($proposal->id)
        ->and($participant->own_revenue_budget_id)->toBe($budget->id)
        ->and($participant->own_revenue_activity_id)->toBe($activity->id)
        ->and($proposal->corrections->sole()->old_value)->toBe('10.0000')
        ->and($proposal->corrections->sole()->new_value)->toBe('12.0000')
        ->and($technicalNeed->corrections)->toHaveCount(1)
        ->and($budget->proposals)->toHaveCount(1)
        ->and($budget->planningRoutes)->toHaveCount(1)
        ->and($budget->travelDestinations)->toHaveCount(1)
        ->and($budget->travelRates)->toHaveCount(1);
});

test('stable keys may repeat between proposal versions but not inside one version', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $firstProposal = OwnRevenueProposal::factory()->for($budget, 'budget')->create(['version_number' => 1]);
    $secondProposal = OwnRevenueProposal::factory()
        ->for($budget, 'budget')
        ->for($firstProposal, 'basedOnProposal')
        ->create(['version_number' => 2]);

    OwnRevenueProposalTechnicalNeed::factory()
        ->for($firstProposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['stable_key' => 'shared-key']);
    OwnRevenueProposalTechnicalNeed::factory()
        ->for($secondProposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['stable_key' => 'shared-key']);

    expect(fn () => OwnRevenueProposalTechnicalNeed::factory()
        ->for($firstProposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create(['stable_key' => 'shared-key']))->toThrow(QueryException::class);
});

test('travel participant stable keys are unique across one proposal', function () {
    $proposal = OwnRevenueProposal::factory()->create();
    $budget = $proposal->budget;
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $firstCommission = OwnRevenueProposalTravelCommission::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create();
    $secondCommission = OwnRevenueProposalTravelCommission::factory()
        ->for($proposal, 'proposal')
        ->for($budget, 'budget')
        ->for($activity, 'activity')
        ->create();

    OwnRevenueProposalTravelParticipant::factory()
        ->for($firstCommission, 'commission')
        ->create(['stable_key' => 'participant-1']);

    expect(fn () => OwnRevenueProposalTravelParticipant::factory()
        ->for($secondCommission, 'commission')
        ->create(['stable_key' => 'participant-1']))->toThrow(QueryException::class);
});
