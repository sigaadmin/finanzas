<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function executionWorkspaceUser(UserRole $role): User
{
    $email = sprintf('execution-workspace-%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, manager: User, source: ExpenseClassification, destination: ExpenseClassification} */
function executionWorkspaceFixture(): array
{
    $manager = executionWorkspaceUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2026,
        'status' => OwnRevenueBudgetStatus::InitialAuthorized,
    ]);
    $proposal = OwnRevenueProposal::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'total_amount_cents' => 15_000,
    ]);
    OwnRevenueInitialBudget::query()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_proposal_id' => $proposal->id,
        'total_amount_cents' => 15_000,
        'source_fingerprint' => str_repeat('a', 64),
        'authorization_fingerprint' => str_repeat('b', 64),
        'snapshot' => ['reconciliation' => ['groups' => [[
            'specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '15000',
        ]]]],
        'authorized_by' => $manager->id,
        'authorized_at' => now(),
    ]);
    $classification = static fn (string $code, string $name): ExpenseClassification => ExpenseClassification::query()->create([
        'fiscal_year' => 2026,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '21000',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => substr($code, 0, 3).'00',
        'generic_item_name' => $name,
        'specific_item_code' => $code,
        'specific_item_name' => $name,
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);

    return [
        'budget' => $budget,
        'manager' => $manager,
        'source' => $classification('21101', 'Materiales y útiles de oficina'),
        'destination' => $classification('21201', 'Materiales de impresión'),
    ];
}

test('the execution workspace shows modified balances and available destinations', function () {
    $this->withoutVite();
    ['budget' => $budget, 'manager' => $manager, 'destination' => $destination] = executionWorkspaceFixture();

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/execution/show')
            ->where('budget.id', $budget->id)
            ->where('budget.status', 'initial_authorized')
            ->where('summary.initial_amount_cents', '15000')
            ->where('summary.modified_amount_cents', '15000')
            ->where('summary.available_amount_cents', '15000')
            ->has('lines', 1)
            ->where('lines.0.specific_item_code', '21101')
            ->where('lines.0.month', 5)
            ->where('lines.0.available_amount_cents', '15000')
            ->where('classifications.1.id', $destination->id)
            ->where('permissions.manage', true)
            ->has('modifications', 0));
});

test('auditors can consult execution but cannot register modifications', function () {
    $this->withoutVite();
    ['budget' => $budget] = executionWorkspaceFixture();
    $auditor = executionWorkspaceUser(UserRole::FinanceAuditor);

    $this->actingAs($auditor)
        ->get(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('permissions.manage', false));
});

test('a modification can be registered from the execution workspace', function () {
    ['budget' => $budget, 'manager' => $manager, 'destination' => $destination] = executionWorkspaceFixture();
    $this->actingAs($manager)->get(route('finance.own-revenue.budgets.execution.show', $budget));
    $sourceLineId = $budget->modifiedBudgetLines()->sole()->id;

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.execution.modifications.store', $budget), [
            'type' => 'transfer',
            'source_line_id' => $sourceLineId,
            'destination_expense_classification_id' => $destination->id,
            'destination_month' => 5,
            'amount_cents' => 4_000,
            'reason' => 'Se requiere reforzar el material de impresión.',
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('own_revenue_budget_modifications', [
        'own_revenue_budget_id' => $budget->id,
        'type' => 'transfer',
        'amount_cents' => 4_000,
        'recorded_by' => $manager->id,
    ]);
});

test('expense dossiers can be created and their sufficiency advanced from the execution workspace', function () {
    $this->withoutVite();
    ['budget' => $budget, 'manager' => $manager] = executionWorkspaceFixture();
    $assistant = executionWorkspaceUser(UserRole::FinanceAssistant);
    $this->actingAs($assistant)->get(route('finance.own-revenue.budgets.execution.show', $budget));
    $line = $budget->modifiedBudgetLines()->sole();

    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.store', $budget), [
            'own_revenue_modified_budget_line_id' => $line->id,
            'concept' => 'Compra de material para actividades académicas',
            'amount_cents' => 4_000,
            'purchase_responsibility' => 'cren',
            'external_reference' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertSessionHasNoErrors();

    $dossier = $budget->expenseDossiers()->sole();
    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.sufficiency-request', [$budget, $dossier]))
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget));
    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.sufficiency-confirmation', [$budget, $dossier]))
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget));

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('expense_dossiers', 1)
            ->where('expense_dossiers.0.folio', 'IP-2026-0001')
            ->where('expense_dossiers.0.status', 'sufficiency_confirmed')
            ->where('expense_dossiers.0.line.specific_item_code', '21101')
            ->where('summary.committed_amount_cents', '4000')
            ->where('permissions.create_expense_dossier', true)
            ->where('permissions.confirm_expense_sufficiency', true));
});

test('budgets without an authorized initial budget do not expose execution', function () {
    $manager = executionWorkspaceUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create(['status' => OwnRevenueBudgetStatus::Draft]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.execution.show', $budget))
        ->assertNotFound();
});
