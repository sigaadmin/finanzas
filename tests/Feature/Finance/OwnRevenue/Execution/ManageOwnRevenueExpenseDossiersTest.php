<?php

use App\Actions\Finance\OwnRevenue\Execution\ConfirmExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\CreateOwnRevenueExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpenseSufficiency;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueProposal;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueBudgetBalance;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExecutionViewData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function expenseDossierUser(UserRole $role): User
{
    $email = sprintf('expense-dossier-%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, line: OwnRevenueModifiedBudgetLine, manager: User, assistant: User} */
function expenseDossierFixture(): array
{
    $manager = expenseDossierUser(UserRole::FinanceManager);
    $assistant = expenseDossierUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2026,
        'status' => OwnRevenueBudgetStatus::InitialAuthorized,
    ]);
    $proposal = OwnRevenueProposal::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'total_amount_cents' => 10_000,
    ]);
    $initialBudget = OwnRevenueInitialBudget::query()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_proposal_id' => $proposal->id,
        'total_amount_cents' => 10_000,
        'source_fingerprint' => str_repeat('a', 64),
        'authorization_fingerprint' => str_repeat('b', 64),
        'snapshot' => ['reconciliation' => ['groups' => [[
            'specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '10000',
        ]]]],
        'authorized_by' => $manager->id,
        'authorized_at' => now(),
    ]);
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => 2026,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '21000',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales y útiles de oficina',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales y útiles de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
    $line = OwnRevenueModifiedBudgetLine::query()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'expense_classification_id' => $classification->id,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales y útiles de oficina',
        'month' => 5,
        'initial_amount_cents' => 10_000,
    ]);

    return compact('budget', 'line', 'manager', 'assistant');
}

/** @return array{concept: string, amount_cents: int, purchase_responsibility: string, external_reference: null, notes: string} */
function expenseDossierData(int $amountCents = 4_000): array
{
    return [
        'concept' => 'Adquisición de materiales de oficina',
        'amount_cents' => $amountCents,
        'purchase_responsibility' => OwnRevenuePurchaseResponsibility::Cren->value,
        'external_reference' => null,
        'notes' => 'Material requerido para actividades administrativas.',
    ];
}

test('an assistant can create an audited draft expense dossier', function () {
    ['budget' => $budget, 'line' => $line, 'assistant' => $assistant] = expenseDossierFixture();

    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());

    expect($dossier->folio)->toBe('IP-2026-0001')
        ->and($dossier->status)->toBe(OwnRevenueExpenseDossierStatus::Draft)
        ->and($dossier->purchase_responsibility)->toBe(OwnRevenuePurchaseResponsibility::Cren)
        ->and($dossier->amount_cents)->toBe(4_000)
        ->and($dossier->transitions()->sole()->to_status)->toBe(OwnRevenueExpenseDossierStatus::Draft)
        ->and(app(OwnRevenueBudgetBalance::class)->availableCents($line->fresh()))->toBe(10_000);
});

test('requesting sufficiency reserves available balance', function () {
    ['budget' => $budget, 'line' => $line, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());

    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::SufficiencyRequested)
        ->and($balances->reservedCents($line->fresh()))->toBe(4_000)
        ->and($balances->committedCents($line->fresh()))->toBe(0)
        ->and($balances->availableCents($line->fresh()))->toBe(6_000)
        ->and($dossier->transitions()->count())->toBe(2);

    $viewData = app(OwnRevenueExecutionViewData::class)->forBudget($budget->fresh());
    expect($viewData['summary']['reserved_amount_cents'])->toBe('4000')
        ->and($viewData['summary']['committed_amount_cents'])->toBe('0')
        ->and($viewData['summary']['available_amount_cents'])->toBe('6000')
        ->and($viewData['lines'][0]['reserved_amount_cents'])->toBe('4000');
});

test('sufficiency cannot reserve more than the line has available', function () {
    ['budget' => $budget, 'line' => $line, 'assistant' => $assistant] = expenseDossierFixture();
    $first = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData(7_000));
    $second = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData(7_000));
    app(RequestExpenseSufficiency::class)->handle($first, $assistant);

    expect(fn () => app(RequestExpenseSufficiency::class)->handle($second, $assistant))
        ->toThrow(ValidationException::class);
    expect($second->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Draft)
        ->and(app(OwnRevenueBudgetBalance::class)->reservedCents($line->fresh()))->toBe(7_000)
        ->and(app(OwnRevenueBudgetBalance::class)->availableCents($line->fresh()))->toBe(3_000);
});

test('confirming sufficiency converts the reservation into a commitment without double counting', function () {
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);

    app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::SufficiencyConfirmed)
        ->and($balances->reservedCents($line->fresh()))->toBe(0)
        ->and($balances->committedCents($line->fresh()))->toBe(4_000)
        ->and($balances->availableCents($line->fresh()))->toBe(6_000)
        ->and($dossier->transitions()->count())->toBe(3);
});

test('only administrators can confirm sufficiency and transitions must follow their order', function () {
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());

    expect(fn () => app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager))
        ->toThrow(ValidationException::class);
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    expect(fn () => app(ConfirmExpenseSufficiency::class)->handle($dossier, $assistant))
        ->toThrow(AuthorizationException::class);
});

test('auditors cannot create or advance expense dossiers', function () {
    ['budget' => $budget, 'line' => $line] = expenseDossierFixture();
    $auditor = expenseDossierUser(UserRole::FinanceAuditor);

    expect(fn () => app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $auditor, expenseDossierData()))
        ->toThrow(AuthorizationException::class);
    expect(OwnRevenueExpenseDossier::query()->count())->toBe(0);
});
