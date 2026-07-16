<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\OwnRevenueProposalStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueTechnicalSheetNeed;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposalTechnicalNeed;
use App\Models\User;

function technicalPlanningUser(UserRole $role): User
{
    $email = 'technical-planning-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function technicalPlanningClassification(OwnRevenueBudget $budget, string $code = '21101'): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $budget->fiscal_year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales de oficina',
        'specific_item_code' => $code,
        'specific_item_name' => 'Materiales de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}

/** @return array{budget: OwnRevenueBudget, proposal: OwnRevenueProposal, activity: OwnRevenueActivity, classification: ExpenseClassification, manager: User} */
function technicalPlanningFixture(OwnRevenueProposalStatus $status = OwnRevenueProposalStatus::Draft): array
{
    $manager = technicalPlanningUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $proposal = OwnRevenueProposal::factory()->for($budget, 'budget')->for($manager, 'creator')->create(['status' => $status]);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $classification = technicalPlanningClassification($budget);

    return compact('budget', 'proposal', 'activity', 'classification', 'manager');
}

/** @return array<string, mixed> */
function technicalPlanningPayload(OwnRevenueActivity $activity, ExpenseClassification $classification, array $overrides = []): array
{
    return [
        'own_revenue_activity_id' => $activity->id,
        'expense_classification_id' => $classification->id,
        'sequence' => '1',
        'quantity' => '2.5',
        'unit' => 'PIEZA',
        'description' => 'Material para actividades académicas',
        'unit_price' => '100.25',
        'budget_amount_cents' => 25_063,
        'budget_month' => 4,
        'impact_on_goals' => 'Permite atender la meta anual.',
        'sort_order' => 3,
        ...$overrides,
    ];
}

test('a finance assistant creates a calculated technical need in the institutional region', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'classification' => $classification] = technicalPlanningFixture();
    $assistant = technicalPlanningUser(UserRole::FinanceAssistant);

    $this->actingAs($assistant)->post(route('finance.own-revenue.budgets.proposals.technical-needs.store', [
        $budget, $proposal,
    ]), [
        ...technicalPlanningPayload($activity, $classification),
        'region_code' => '99-999',
    ])->assertSessionHasNoErrors();

    $need = OwnRevenueProposalTechnicalNeed::query()->sole();
    expect($need->reference_amount_cents)->toBe(25_063)
        ->and($need->unit_price_cents)->toBe(10_025)
        ->and($need->budget_amount_cents)->toBe(25_063)
        ->and($need->specific_item_code)->toBe('21101')
        ->and($need->region_code)->toBe('02-001')
        ->and($need->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($proposal->fresh()->total_amount_cents)->toBe(25_063);
});

test('a definitive total different from the reference requires and records justification', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'classification' => $classification, 'manager' => $manager] = technicalPlanningFixture();
    $route = route('finance.own-revenue.budgets.proposals.technical-needs.store', [$budget, $proposal]);
    $payload = technicalPlanningPayload($activity, $classification, ['budget_amount_cents' => 30_000]);

    $this->actingAs($manager)->post($route, $payload)
        ->assertSessionHasErrors('override_justification');
    expect(OwnRevenueProposalTechnicalNeed::query()->count())->toBe(0);

    $this->actingAs($manager)->post($route, [
        ...$payload,
        'override_justification' => 'Se ajustó al precio confirmado por el proveedor.',
    ])->assertSessionHasNoErrors();

    $need = OwnRevenueProposalTechnicalNeed::query()->sole();
    $correction = $need->corrections()->sole();
    expect($correction->field)->toBe('budget_amount_cents')
        ->and($correction->old_value)->toBe('25063')
        ->and($correction->new_value)->toBe('30000')
        ->and($correction->justification)->toBe('Se ajustó al precio confirmado por el proveedor.')
        ->and($correction->corrected_by)->toBe($manager->id);
});

test('updating a technical need preserves its stable key and imported origin', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'classification' => $classification, 'manager' => $manager] = technicalPlanningFixture();
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->for($manager, 'createdBy')->create();
    $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::TechnicalSheet,
    ]);
    $source = OwnRevenueTechnicalSheetNeed::factory()
        ->for($file, 'file')->for($budget, 'budget')->for($activity, 'activity')
        ->for($classification, 'expenseClassification')->create();
    $need = OwnRevenueProposalTechnicalNeed::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')
        ->for($classification, 'expenseClassification')->for($source, 'sourceTechnicalSheetNeed')
        ->create(['stable_key' => 'technical-origin-1']);

    $this->actingAs($manager)->put(route('finance.own-revenue.budgets.proposals.technical-needs.update', [
        $budget, $proposal, $need,
    ]), technicalPlanningPayload($activity, $classification, [
        'description' => 'Descripción corregida',
        'budget_amount_cents' => 30_000,
        'override_justification' => 'Importe definitivo revisado.',
    ]))->assertSessionHasNoErrors();

    expect($need->fresh()->stable_key)->toBe('technical-origin-1')
        ->and($need->fresh()->source_technical_sheet_need_id)->toBe($source->id)
        ->and($need->fresh()->description)->toBe('Descripción corregida')
        ->and($proposal->fresh()->total_amount_cents)->toBe(30_000);
});

test('activities and classifications must belong to the proposal budget', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'classification' => $classification, 'manager' => $manager] = technicalPlanningFixture();
    $otherBudget = OwnRevenueBudget::factory()->create();
    $otherActivity = OwnRevenueActivity::factory()->for($otherBudget, 'budget')->create();
    $otherClassification = technicalPlanningClassification($otherBudget, '21201');
    $route = route('finance.own-revenue.budgets.proposals.technical-needs.store', [$budget, $proposal]);

    $this->actingAs($manager)->post($route, technicalPlanningPayload($otherActivity, $classification))
        ->assertSessionHasErrors('own_revenue_activity_id');
    $this->actingAs($manager)->post($route, technicalPlanningPayload($activity, $otherClassification))
        ->assertSessionHasErrors('expense_classification_id');

    expect(OwnRevenueProposalTechnicalNeed::query()->count())->toBe(0);
});

test('a draft technical need can be deleted', function () {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'manager' => $manager] = technicalPlanningFixture();
    $need = OwnRevenueProposalTechnicalNeed::factory()
        ->for($proposal, 'proposal')->for($budget, 'budget')->for($activity, 'activity')->create();
    $proposal->update(['total_amount_cents' => $need->budget_amount_cents]);

    $this->actingAs($manager)->delete(route('finance.own-revenue.budgets.proposals.technical-needs.destroy', [
        $budget, $proposal, $need,
    ]))->assertSessionHasNoErrors();

    expect($need->fresh())->toBeNull()
        ->and($proposal->fresh()->total_amount_cents)->toBe(0);
});

test('immutable proposals and auditors cannot change technical needs', function (OwnRevenueProposalStatus $status, UserRole $role) {
    ['budget' => $budget, 'proposal' => $proposal, 'activity' => $activity, 'classification' => $classification] = technicalPlanningFixture($status);
    $user = technicalPlanningUser($role);

    $this->actingAs($user)->post(route('finance.own-revenue.budgets.proposals.technical-needs.store', [
        $budget, $proposal,
    ]), technicalPlanningPayload($activity, $classification))->assertForbidden();
})->with([
    'calculated manager' => [OwnRevenueProposalStatus::Calculated, UserRole::FinanceManager],
    'adjusted manager' => [OwnRevenueProposalStatus::Adjusted, UserRole::FinanceManager],
    'draft auditor' => [OwnRevenueProposalStatus::Draft, UserRole::FinanceAuditor],
]);
