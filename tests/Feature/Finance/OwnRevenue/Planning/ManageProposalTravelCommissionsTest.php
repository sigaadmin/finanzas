<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelParticipant;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelDestination;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueTravelRate;
use App\Models\User;

function travelPlanningUser(UserRole $role): User
{
    $email = 'travel-planning-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, proposal: OwnRevenueProposal, activity: OwnRevenueActivity, destination: OwnRevenueTravelDestination, exactRate: OwnRevenueTravelRate, fallbackRate: OwnRevenueTravelRate, manager: User} */
function travelPlanningFixture(OwnRevenueProposalStatus $status = OwnRevenueProposalStatus::Draft): array
{
    $manager = travelPlanningUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create(['uma_value' => '117.3100']);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create(['status' => $status]);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $destination = OwnRevenueTravelDestination::factory()->for($budget, 'budget')->create([
        'destination' => 'Chetumal',
        'normalized_destination' => 'chetumal',
        'food_zone' => 2,
        'lodging_zone' => 3,
    ]);
    $exactRate = OwnRevenueTravelRate::factory()->for($budget, 'budget')->create([
        'position' => 'Docente',
        'normalized_position' => 'docente',
        'food_zone' => 2,
        'lodging_zone' => 3,
        'per_diem_uma' => '10',
        'lodging_uma' => '8',
    ]);
    $fallbackRate = OwnRevenueTravelRate::factory()->for($budget, 'budget')->create([
        'position' => 'Puestos no considerados en los anteriores',
        'normalized_position' => 'puestos no considerados en los anteriores',
        'food_zone' => 2,
        'lodging_zone' => 3,
        'per_diem_uma' => '5',
        'lodging_uma' => '4',
        'is_fallback' => true,
    ]);

    return compact('budget', 'proposal', 'activity', 'destination', 'exactRate', 'fallbackRate', 'manager');
}

/** @return array<string, mixed> */
function travelCommissionPayload(OwnRevenueActivity $activity, OwnRevenueTravelDestination $destination, array $overrides = []): array
{
    return [
        'own_revenue_activity_id' => $activity->id,
        'own_revenue_travel_destination_id' => $destination->id,
        'commission_date_label' => 'AGOSTO',
        'operational_month' => 8,
        'budget_month' => 8,
        'reason' => 'Participar en jornada académica',
        'flight_amount_cents' => 100_000,
        'sort_order' => 1,
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function travelParticipantPayload(array $overrides = []): array
{
    return [
        'person_name' => 'María del Carmen Pérez',
        'position' => '  DOCENTE ',
        'commission_days' => '2',
        'sort_order' => 1,
        ...$overrides,
    ];
}

test('travel destinations and rates can be maintained inside one budget', function () {
    ['budget' => $budget, 'manager' => $manager] = travelPlanningFixture();

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.travel-destinations.store', $budget), [
        'destination' => '  Cancún ', 'food_zone' => 4, 'lodging_zone' => 5, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $destination = OwnRevenueTravelDestination::query()->where('normalized_destination', 'cancún')->sole();
    expect($destination->destination)->toBe('Cancún')->and($destination->food_zone)->toBe(4);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.travel-rates.store', $budget), [
        'position' => '  DIRECTIVO ', 'food_zone' => 4, 'lodging_zone' => 5,
        'per_diem_uma' => '12', 'lodging_uma' => '9', 'is_fallback' => false, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    $rate = OwnRevenueTravelRate::query()->where('normalized_position', 'directivo')->sole();
    expect($rate->per_diem_uma)->toBe('12.0000');

    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.travel-rates.destroy', [$budget, $rate]))
        ->assertSessionHasNoErrors();
    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.travel-destinations.destroy', [$budget, $destination]))
        ->assertSessionHasNoErrors();
});

test('destination zones exact rate and budget uma calculate participants on the server', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'destination' => $destination, 'exactRate' => $exactRate, 'manager' => $manager] = travelPlanningFixture();
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.store', [$budget, $proposal]),
        travelCommissionPayload($activity, $destination))->assertSessionHasNoErrors();
    $commission = OwnRevenueProposalTravelCommission::query()->sole();

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.participants.store', [$budget, $proposal, $commission]),
        travelParticipantPayload())->assertSessionHasNoErrors();

    $participant = OwnRevenueProposalTravelParticipant::query()->sole();
    expect($commission->fresh()->food_zone)->toBe(2)
        ->and($commission->fresh()->lodging_zone)->toBe(3)
        ->and($commission->fresh()->uma_value)->toBe('117.3100')
        ->and($participant->own_revenue_travel_rate_id)->toBe($exactRate->id)
        ->and($participant->per_diem_amount_cents)->toBe(234_620)
        ->and($participant->lodging_amount_cents)->toBe(93_848)
        ->and($participant->total_amount_cents)->toBe(328_468)
        ->and($commission->fresh()->participants_amount_cents)->toBe(328_468)
        ->and($commission->fresh()->total_amount_cents)->toBe(428_468)
        ->and($proposal->fresh()->total_amount_cents)->toBe(428_468);
});

test('an unknown position uses the active fallback rate and flight is added only once', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'destination' => $destination, 'fallbackRate' => $fallbackRate, 'manager' => $manager] = travelPlanningFixture();
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.store', [$budget, $proposal]),
        travelCommissionPayload($activity, $destination))->assertSessionHasNoErrors();
    $commission = OwnRevenueProposalTravelCommission::query()->sole();

    $participantsRoute = route('finance.own-revenue.budgets.proposals.travel-commissions.participants.store', [$budget, $proposal, $commission]);
    $this->actingAs($manager)->post($participantsRoute, travelParticipantPayload())->assertSessionHasNoErrors();
    $this->actingAs($manager)->post($participantsRoute, travelParticipantPayload([
        'person_name' => 'Juan Martínez', 'position' => 'Analista especializado', 'sort_order' => 2,
    ]))->assertSessionHasNoErrors();

    $fallbackParticipant = OwnRevenueProposalTravelParticipant::query()->where('person_name', 'Juan Martínez')->sole();
    expect($fallbackParticipant->own_revenue_travel_rate_id)->toBe($fallbackRate->id)
        ->and($fallbackParticipant->total_amount_cents)->toBe(164_234)
        ->and($commission->fresh()->participants_amount_cents)->toBe(492_702)
        ->and($commission->fresh()->total_amount_cents)->toBe(592_702);
});

test('manual zone and rate overrides require justification and record corrections', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'destination' => $destination, 'manager' => $manager] = travelPlanningFixture();
    $commissionRoute = route('finance.own-revenue.budgets.proposals.travel-commissions.store', [$budget, $proposal]);
    $override = travelCommissionPayload($activity, $destination, ['food_zone' => 9]);
    $this->actingAs($manager)->post($commissionRoute, $override)->assertSessionHasErrors('override_justification');
    $this->actingAs($manager)->post($commissionRoute, [
        ...$override, 'override_justification' => 'La sede aplica una zona excepcional.',
    ])->assertSessionHasNoErrors();
    $commission = OwnRevenueProposalTravelCommission::query()->sole();
    expect($commission->corrections()->where('field', 'food_zone')->sole()->old_value)->toBe('2');

    OwnRevenueTravelRate::factory()->for($budget, 'budget')->create([
        'position' => 'Docente', 'normalized_position' => 'docente',
        'food_zone' => 9, 'lodging_zone' => 3, 'per_diem_uma' => '10', 'lodging_uma' => '8',
    ]);

    $participantRoute = route('finance.own-revenue.budgets.proposals.travel-commissions.participants.store', [$budget, $proposal, $commission]);
    $rateOverride = travelParticipantPayload(['per_diem_uma' => '11', 'lodging_uma' => '8']);
    $this->actingAs($manager)->post($participantRoute, $rateOverride)->assertSessionHasErrors('override_justification');
    $this->actingAs($manager)->post($participantRoute, [
        ...$rateOverride, 'override_justification' => 'Tarifa autorizada para esta comisión.',
    ])->assertSessionHasNoErrors();
    expect(OwnRevenueProposalTravelParticipant::query()->sole()->corrections()->where('field', 'per_diem_uma')->exists())->toBeTrue();
});

test('commissions and participants can be reordered updated and deleted', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'destination' => $destination, 'manager' => $manager] = travelPlanningFixture();
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.store', [$budget, $proposal]),
        travelCommissionPayload($activity, $destination))->assertSessionHasNoErrors();
    $commission = OwnRevenueProposalTravelCommission::query()->sole();
    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.participants.store', [$budget, $proposal, $commission]),
        travelParticipantPayload())->assertSessionHasNoErrors();
    $participant = OwnRevenueProposalTravelParticipant::query()->sole();

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.proposals.travel-commissions.participants.update', [$budget, $proposal, $commission, $participant]),
        travelParticipantPayload(['sort_order' => 7]))->assertSessionHasNoErrors();
    expect($participant->fresh()->sort_order)->toBe(7);

    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.proposals.travel-commissions.participants.destroy', [$budget, $proposal, $commission, $participant]))
        ->assertSessionHasNoErrors();
    expect($commission->fresh()->total_amount_cents)->toBe(100_000);
    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.proposals.travel-commissions.destroy', [$budget, $proposal, $commission]))
        ->assertSessionHasNoErrors();
    expect($commission->fresh())->toBeNull();
});

test('immutable proposals and auditors cannot change travel commissions', function (OwnRevenueProposalStatus $status, UserRole $role) {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'destination' => $destination] = travelPlanningFixture($status);
    $user = travelPlanningUser($role);

    $this->actingAs($user)->post(route('finance.own-revenue.budgets.proposals.travel-commissions.store', [$budget, $proposal]),
        travelCommissionPayload($activity, $destination))->assertForbidden();
})->with([
    'calculated manager' => [OwnRevenueProposalStatus::Calculated, UserRole::FinanceManager],
    'adjusted manager' => [OwnRevenueProposalStatus::Adjusted, UserRole::FinanceManager],
    'draft auditor' => [OwnRevenueProposalStatus::Draft, UserRole::FinanceAuditor],
]);
