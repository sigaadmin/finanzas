<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalFuelNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTravelCommission;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueRoute;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Planning\OwnRevenueCutReconciliation;
use Inertia\Testing\AssertableInertia as Assert;

function planningNavigationUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'planning-navigation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function planningNavigationClassification(int $fiscalYear): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $fiscalYear,
        'chapter_code' => '2000', 'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100', 'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100', 'generic_item_name' => 'Materiales de oficina',
        'specific_item_code' => '21101', 'specific_item_name' => 'Materiales de oficina',
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
}

test('planning page shows its prerequisite checklist before a proposal exists', function () {
    $manager = planningNavigationUser();
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/planning/show')
            ->where('budget.id', $budget->id)
            ->where('proposal', null)
            ->where('readiness.ready', false)
            ->has('readiness.blockers')
            ->where('permissions.create', true)
            ->where('permissions.edit', true));
});

test('planning page selects one budget version and paginates only its requested section', function () {
    $manager = planningNavigationUser();
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $classification = planningNavigationClassification($budget->fiscal_year);
    $older = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create([
        'version_number' => 1, 'status' => OwnRevenueProposalStatus::Calculated,
    ]);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create([
        'version_number' => 2, 'status' => OwnRevenueProposalStatus::Draft,
    ]);
    OwnRevenueProposalTechnicalNeed::factory()->count(27)
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')
        ->for($classification, 'expenseClassification')->sequence(fn ($sequence): array => [
            'description' => 'Necesidad '.($sequence->index + 1),
            'sort_order' => $sequence->index + 1,
            'budget_amount_cents' => 100,
        ])->create();
    $route = OwnRevenueRoute::factory()->for($budget, 'budget')->create();
    OwnRevenueProposalFuelNeed::factory()->for($proposal, 'proposal')->for($budget, 'budget')
        ->for($activity, 'activity')->for($route, 'route')->create(['budget_amount_cents' => 250]);
    OwnRevenueProposalTravelCommission::factory()->for($proposal, 'proposal')->for($budget, 'budget')
        ->for($activity, 'activity')->create(['total_amount_cents' => 350]);
    $foreignBudget = OwnRevenueBudget::factory()->create();
    $foreignProposal = OwnRevenueProposal::factory()->for($foreignBudget, 'budget')->for($manager, 'creator')->create(['version_number' => 9]);
    $foreignActivity = OwnRevenueActivity::factory()->for($foreignBudget, 'budget')->create();
    $foreignClassification = planningNavigationClassification($foreignBudget->fiscal_year);
    OwnRevenueProposalTechnicalNeed::factory()->for($foreignProposal, 'proposal')->for($foreignBudget, 'budget')
        ->for($foreignActivity, 'activity')->for($foreignClassification, 'expenseClassification')
        ->create(['description' => 'No debe aparecer']);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.planning.show', [
            'budget' => $budget,
            'proposal_version' => 2,
            'section' => 'technical',
            'page' => 2,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.id', $proposal->id)
            ->where('proposal.version_number', 2)
            ->where('section', 'technical')
            ->has('versions', 2)
            ->where('summaries.technical.count', 27)
            ->where('summaries.technical.total_amount_cents', '2700')
            ->where('summaries.fuel.count', 1)
            ->where('summaries.travel.count', 1)
            ->where('rows.current_page', 2)
            ->where('rows.total', 27)
            ->has('rows.data', 2)
            ->where('rows.data.0.description', 'Necesidad 26')
            ->where('permissions.edit', true)
            ->where('versions.0.version_number', 2)
            ->where('versions.1.id', $older->id));
});

test('a selected planning detail exposes corrections without leaving the page', function () {
    $manager = planningNavigationUser();
    $budget = OwnRevenueBudget::factory()->create();
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create();
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $classification = planningNavigationClassification($budget->fiscal_year);
    $need = OwnRevenueProposalTechnicalNeed::factory()->for($proposal, 'proposal')->for($budget, 'budget')
        ->for($activity, 'activity')->for($classification, 'expenseClassification')->create();
    $need->corrections()->create([
        'own_revenue_proposal_id' => $proposal->id,
        'field' => 'budget_amount_cents',
        'old_value' => '10000',
        'new_value' => '12000',
        'justification' => 'Cotización actualizada.',
        'corrected_by' => $manager->id,
        'corrected_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.planning.show', [
            'budget' => $budget, 'section' => 'technical', 'detail_id' => $need->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_detail.id', $need->id)
            ->where('selected_detail.title', $need->description)
            ->has('selected_detail.corrections', 1)
            ->where('selected_detail.corrections.0.field', 'Importe definitivo')
            ->where('selected_detail.corrections.0.justification', 'Cotización actualizada.'));
});

test('planning mutation permissions follow role and proposal state', function (UserRole $role, OwnRevenueProposalStatus $status, bool $canCreate, bool $canEdit) {
    $manager = planningNavigationUser();
    $user = planningNavigationUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create(['status' => $status]);

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.create', $canCreate)
            ->where('permissions.edit', $canEdit));
})->with([
    'manager draft' => [UserRole::FinanceManager, OwnRevenueProposalStatus::Draft, true, true],
    'assistant draft' => [UserRole::FinanceAssistant, OwnRevenueProposalStatus::Draft, false, true],
    'auditor draft' => [UserRole::FinanceAuditor, OwnRevenueProposalStatus::Draft, false, false],
    'manager calculated' => [UserRole::FinanceManager, OwnRevenueProposalStatus::Calculated, true, false],
]);

test('planning page does not offer initial authorization after the initial budget was authorized', function () {
    $owner = planningNavigationUser(UserRole::Owner);
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InitialAuthorized,
    ]);
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($owner, 'creator')->create([
        'status' => OwnRevenueProposalStatus::Adjusted,
    ]);
    OwnRevenueInitialBudget::factory()->for($budget, 'budget')->for($proposal, 'proposal')->for($owner, 'authorizer')->create();
    $reconciliation = Mockery::mock(OwnRevenueCutReconciliation::class);
    $reconciliation->shouldReceive('forProposal')->once()->withArgs(fn (OwnRevenueProposal $candidate): bool => $candidate->is($proposal))->andReturn([
        'ready' => true,
        'blockers' => [],
        'fingerprint' => str_repeat('c', 64),
    ]);
    app()->instance(OwnRevenueCutReconciliation::class, $reconciliation);

    $this->actingAs($owner)
        ->get(route('finance.own-revenue.budgets.planning.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budget.status', OwnRevenueBudgetStatus::InitialAuthorized->value)
            ->where('initial_budget.id', fn (int $id): bool => $id > 0)
            ->where('permissions.authorize', false));
});
