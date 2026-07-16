<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\User;

function fuelPlanningUser(UserRole $role): User
{
    $email = 'fuel-planning-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, proposal: OwnRevenueProposal, activity: OwnRevenueActivity, route: OwnRevenueRoute, manager: User} */
function fuelPlanningFixture(OwnRevenueProposalStatus $status = OwnRevenueProposalStatus::Draft): array
{
    $manager = fuelPlanningUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create([
        'fuel_price_per_liter' => '24.5000',
        'fuel_budget_month' => 4,
    ]);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create(['status' => $status]);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $route = OwnRevenueRoute::factory()->for($budget, 'budget')->create([
        'origin' => 'Felipe Carrillo Puerto',
        'normalized_origin' => 'felipe carrillo puerto',
        'destination' => 'Chetumal',
        'normalized_destination' => 'chetumal',
        'one_way_kilometers' => '50.0000',
        'additional_kilometers' => '0.0000',
    ]);

    return compact('budget', 'proposal', 'activity', 'route', 'manager');
}

/** @return array<string, mixed> */
function fuelPlanningPayload(OwnRevenueActivity $activity, OwnRevenueRoute $route, array $overrides = []): array
{
    return [
        'own_revenue_activity_id' => $activity->id,
        'own_revenue_route_id' => $route->id,
        'commission_date_label' => 'AGOSTO',
        'operational_month' => 8,
        'reason' => 'Traslado para supervisión académica',
        'vehicle_model' => 'Unidad institucional',
        'kilometers_per_liter' => '10',
        'sort_order' => 2,
        ...$overrides,
    ];
}

test('a catalog route can be created updated and removed inside one budget', function () {
    ['budget' => $budget, 'manager' => $manager] = fuelPlanningFixture();
    $payload = [
        'origin' => '  Felipe Carrillo Puerto ',
        'destination' => ' Cancún ',
        'one_way_kilometers' => '220',
        'additional_kilometers' => '5',
        'is_active' => true,
        'sort_order' => 3,
    ];

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.planning-routes.store', $budget), $payload)
        ->assertSessionHasNoErrors();
    $route = OwnRevenueRoute::query()->where('normalized_destination', 'cancún')->sole();
    expect($route->origin)->toBe('Felipe Carrillo Puerto')
        ->and($route->normalized_origin)->toBe('felipe carrillo puerto')
        ->and($route->destination)->toBe('Cancún');

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.planning-routes.update', [$budget, $route]), [
        ...$payload,
        'one_way_kilometers' => '225',
    ])->assertSessionHasNoErrors();
    expect($route->fresh()->one_way_kilometers)->toBe('225.0000');

    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.planning-routes.destroy', [$budget, $route]))
        ->assertSessionHasNoErrors();
    expect($route->fresh())->toBeNull();
});

test('a route is reused and all fuel calculations are persisted server side', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'route' => $route] = fuelPlanningFixture();
    $assistant = fuelPlanningUser(UserRole::FinanceAssistant);

    $this->actingAs($assistant)->post(route('finance.own-revenue.budgets.proposals.fuel-needs.store', [
        $budget, $proposal,
    ]), fuelPlanningPayload($activity, $route))->assertSessionHasNoErrors();

    $need = OwnRevenueProposalFuelNeed::query()->sole();
    expect($need->own_revenue_route_id)->toBe($route->id)
        ->and($need->operational_month)->toBe(8)
        ->and($need->budget_month)->toBe(4)
        ->and($need->fuel_price)->toBe('24.5000')
        ->and($need->total_kilometers)->toBe('100.0000')
        ->and($need->liters)->toBe('10.0000')
        ->and($need->mathematical_amount_cents)->toBe(24_500)
        ->and($need->rounded_amount_cents)->toBe(24_500)
        ->and($need->budget_amount_cents)->toBe(25_000)
        ->and($need->rounding_difference_cents)->toBe(500)
        ->and($proposal->fresh()->total_amount_cents)->toBe(25_000);
});

test('a route distance override requires justification and records the correction', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'route' => $route, 'manager' => $manager] = fuelPlanningFixture();
    $storeRoute = route('finance.own-revenue.budgets.proposals.fuel-needs.store', [$budget, $proposal]);
    $payload = fuelPlanningPayload($activity, $route, [
        'outbound_kilometers' => '60',
        'return_kilometers' => '60',
    ]);

    $this->actingAs($manager)->post($storeRoute, $payload)
        ->assertSessionHasErrors('override_justification');
    $this->actingAs($manager)->post($storeRoute, [
        ...$payload,
        'override_justification' => 'El acceso alterno aumenta el recorrido.',
    ])->assertSessionHasNoErrors();

    $need = OwnRevenueProposalFuelNeed::query()->sole();
    $correction = $need->corrections()->where('field', 'total_kilometers')->sole();
    expect($correction->old_value)->toBe('100.0000')
        ->and($correction->new_value)->toBe('120.0000')
        ->and($correction->justification)->toBe('El acceso alterno aumenta el recorrido.');
});

test('price and calculated budget overrides require justification while exact fifty pesos do not increment', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'route' => $route, 'manager' => $manager] = fuelPlanningFixture();
    $storeRoute = route('finance.own-revenue.budgets.proposals.fuel-needs.store', [$budget, $proposal]);
    $priceOverride = fuelPlanningPayload($activity, $route, ['fuel_price' => '25.00']);

    $this->actingAs($manager)->post($storeRoute, $priceOverride)
        ->assertSessionHasErrors('override_justification');
    $this->actingAs($manager)->post($storeRoute, [
        ...$priceOverride,
        'override_justification' => 'Precio confirmado para la fecha del recorrido.',
    ])->assertSessionHasNoErrors();

    $need = OwnRevenueProposalFuelNeed::query()->sole();
    expect($need->mathematical_amount_cents)->toBe(25_000)
        ->and($need->rounded_amount_cents)->toBe(25_000)
        ->and($need->budget_amount_cents)->toBe(25_000)
        ->and($need->rounding_difference_cents)->toBe(0);

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.proposals.fuel-needs.update', [
        $budget, $proposal, $need,
    ]), fuelPlanningPayload($activity, $route, [
        'fuel_price' => '25.00',
        'budget_amount_cents' => 30_000,
    ]))->assertSessionHasErrors('override_justification');
});

test('fuel needs can be reordered updated and deleted from a draft', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'route' => $route, 'manager' => $manager] = fuelPlanningFixture();
    $need = OwnRevenueProposalFuelNeed::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')->for($route, 'route')
        ->create(['sort_order' => 1]);

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.proposals.fuel-needs.update', [
        $budget, $proposal, $need,
    ]), fuelPlanningPayload($activity, $route, ['sort_order' => 9]))->assertSessionHasNoErrors();
    expect($need->fresh()->sort_order)->toBe(9);

    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.proposals.fuel-needs.destroy', [
        $budget, $proposal, $need,
    ]))->assertSessionHasNoErrors();
    expect($need->fresh())->toBeNull();
});

test('immutable proposals and auditors cannot change fuel needs', function (OwnRevenueProposalStatus $status, UserRole $role) {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'route' => $route] = fuelPlanningFixture($status);
    $user = fuelPlanningUser($role);

    $this->actingAs($user)->post(route('finance.own-revenue.budgets.proposals.fuel-needs.store', [
        $budget, $proposal,
    ]), fuelPlanningPayload($activity, $route))->assertForbidden();
})->with([
    'calculated manager' => [OwnRevenueProposalStatus::Calculated, UserRole::FinanceManager],
    'adjusted manager' => [OwnRevenueProposalStatus::Adjusted, UserRole::FinanceManager],
    'draft auditor' => [OwnRevenueProposalStatus::Draft, UserRole::FinanceAuditor],
]);
