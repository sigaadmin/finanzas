<?php

use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByBudgetOffice;
use App\Actions\Finance\OwnRevenue\Execution\AuthorizeExpensePaymentByFinance;
use App\Actions\Finance\OwnRevenue\Execution\CancelExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\ConfirmExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\CreateOwnRevenueExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\MarkExpenseDossierPaid;
use App\Actions\Finance\OwnRevenue\Execution\RejectExpenseDossier;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpensePayment;
use App\Actions\Finance\OwnRevenue\Execution\RequestExpenseSufficiency;
use App\Actions\Finance\OwnRevenue\Execution\StartExpensePurchase;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

/** @return array{budget: OwnRevenueBudget, line: OwnRevenueModifiedBudgetLine, manager: User, assistant: User, dossier: OwnRevenueExpenseDossier} */
function paymentRequestedExpenseDossier(): array
{
    Storage::fake('local');
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);
    app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-CREN-2026-001');
    app(RequestExpensePayment::class)->handle($dossier, $assistant, 'SP-CREN-2026-001', [
        UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf'),
    ]);

    return compact('budget', 'line', 'manager', 'assistant', 'dossier');
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

test('an assistant starts the purchase from confirmed sufficiency with an audited reference', function () {
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);

    app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-CREN-2026-001');

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::PurchaseInProgress)
        ->and($dossier->fresh()->purchase_reference)->toBe('OC-CREN-2026-001')
        ->and($dossier->fresh()->purchase_started_at)->not->toBeNull()
        ->and($dossier->transitions()->latest('id')->first()->to_status)->toBe(OwnRevenueExpenseDossierStatus::PurchaseInProgress)
        ->and(app(OwnRevenueBudgetBalance::class)->committedCents($line->fresh()))->toBe(4_000)
        ->and(app(OwnRevenueBudgetBalance::class)->availableCents($line->fresh()))->toBe(6_000);
});

test('requesting payment stores private evidence and keeps the amount committed', function () {
    Storage::fake('local');
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);
    app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-CREN-2026-001');
    $invoice = UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf');

    app(RequestExpensePayment::class)->handle($dossier, $assistant, 'SP-CREN-2026-001', [$invoice]);

    $document = $dossier->documents()->sole();
    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::PaymentRequested)
        ->and($dossier->fresh()->payment_request_reference)->toBe('SP-CREN-2026-001')
        ->and($dossier->fresh()->payment_requested_at)->not->toBeNull()
        ->and($document->original_name)->toBe('factura.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->uploaded_by)->toBe($assistant->id)
        ->and(app(OwnRevenueBudgetBalance::class)->committedCents($line->fresh()))->toBe(4_000);
    Storage::disk('local')->assertExists($document->storage_path);
});

test('purchase and payment stages cannot be skipped and payment requires evidence', function () {
    Storage::fake('local');
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);

    expect(fn () => app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-001'))
        ->toThrow(ValidationException::class);
    app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);
    app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-001');
    expect(fn () => app(RequestExpensePayment::class)->handle($dossier, $assistant, 'SP-001', []))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RequestExpensePayment::class)->handle($dossier, $assistant, 'SP-001', [
        UploadedFile::fake()->create('factura.exe', 20, 'application/pdf'),
    ]))->toThrow(ValidationException::class);
    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::PurchaseInProgress);
});

test('a manager records the finance authorization with its external reference', function () {
    ['dossier' => $dossier, 'manager' => $manager, 'line' => $line] = paymentRequestedExpenseDossier();

    app(AuthorizeExpensePaymentByFinance::class)->handle($dossier, $manager, 'AF-2026-001');

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::FinanceAuthorized)
        ->and($dossier->fresh()->finance_authorization_reference)->toBe('AF-2026-001')
        ->and($dossier->fresh()->finance_authorized_by)->toBe($manager->id)
        ->and($dossier->fresh()->finance_authorized_at)->not->toBeNull()
        ->and($dossier->transitions()->latest('id')->first()->to_status)->toBe(OwnRevenueExpenseDossierStatus::FinanceAuthorized)
        ->and(app(OwnRevenueBudgetBalance::class)->committedCents($line->fresh()))->toBe(4_000);
});

test('only administrators authorize payments and authorization stages cannot be skipped', function () {
    ['dossier' => $dossier, 'manager' => $manager, 'assistant' => $assistant] = paymentRequestedExpenseDossier();

    expect(fn () => app(AuthorizeExpensePaymentByFinance::class)->handle($dossier, $assistant, 'AF-001'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AuthorizeExpensePaymentByBudgetOffice::class)->handle($dossier, $manager, 'AP-001'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(MarkExpenseDossierPaid::class)->handle($dossier, $manager, 'PAGO-001'))
        ->toThrow(ValidationException::class);
});

test('budget office authorization and payment move the commitment to paid without double counting', function () {
    ['dossier' => $dossier, 'manager' => $manager, 'line' => $line] = paymentRequestedExpenseDossier();
    app(AuthorizeExpensePaymentByFinance::class)->handle($dossier, $manager, 'AF-2026-001');

    app(AuthorizeExpensePaymentByBudgetOffice::class)->handle($dossier, $manager, 'AP-2026-001');
    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized)
        ->and($dossier->fresh()->budget_office_authorization_reference)->toBe('AP-2026-001');
    app(MarkExpenseDossierPaid::class)->handle($dossier, $manager, 'PAGO-2026-001');
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Paid)
        ->and($dossier->fresh()->payment_reference)->toBe('PAGO-2026-001')
        ->and($dossier->fresh()->paid_by)->toBe($manager->id)
        ->and($dossier->fresh()->paid_at)->not->toBeNull()
        ->and($dossier->transitions()->count())->toBe(8)
        ->and($balances->committedCents($line->fresh()))->toBe(0)
        ->and($balances->paidCents($line->fresh()))->toBe(4_000)
        ->and($balances->availableCents($line->fresh()))->toBe(6_000);
});

test('an assistant cancels an active dossier and releases its reserved or committed balance', function (string $stage) {
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $dossier = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    if ($stage !== 'draft') {
        app(RequestExpenseSufficiency::class)->handle($dossier, $assistant);
    }
    if ($stage === 'purchase') {
        app(ConfirmExpenseSufficiency::class)->handle($dossier, $manager);
        app(StartExpensePurchase::class)->handle($dossier, $assistant, 'OC-CANCEL-001');
    }

    app(CancelExpenseDossier::class)->handle($dossier, $assistant, 'La adquisición dejó de ser necesaria para el ejercicio.');
    $transition = $dossier->transitions()->latest('id')->firstOrFail();
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Cancelled)
        ->and($transition->reason)->toBe('La adquisición dejó de ser necesaria para el ejercicio.')
        ->and($transition->actor_id)->toBe($assistant->id)
        ->and($balances->reservedCents($line->fresh()))->toBe(0)
        ->and($balances->committedCents($line->fresh()))->toBe(0)
        ->and($balances->availableCents($line->fresh()))->toBe(10_000);

    $this->actingAs($assistant);
    $viewData = app(OwnRevenueExecutionViewData::class)->forBudget($budget->fresh());
    expect($viewData['permissions']['cancel_expense_dossier'])->toBeTrue()
        ->and($viewData['permissions']['reject_expense_dossier'])->toBeFalse()
        ->and($viewData['expense_dossiers'][0]['latest_transition']['to_status'])->toBe('cancelled')
        ->and($viewData['expense_dossiers'][0]['latest_transition']['reason'])->toBe('La adquisición dejó de ser necesaria para el ejercicio.')
        ->and($viewData['expense_dossiers'][0]['latest_transition']['actor_name'])->toBe($assistant->name);
})->with(['draft', 'reserved' => 'requested', 'committed' => 'purchase']);

test('a manager rejects a payment review and releases the commitment with an audited reason', function () {
    ['dossier' => $dossier, 'manager' => $manager, 'assistant' => $assistant, 'line' => $line] = paymentRequestedExpenseDossier();

    expect(fn () => app(RejectExpenseDossier::class)->handle($dossier, $assistant, 'No corresponde al concepto autorizado.'))
        ->toThrow(AuthorizationException::class);

    app(RejectExpenseDossier::class)->handle($dossier, $manager, 'La factura no corresponde al concepto autorizado.');
    $transition = $dossier->transitions()->latest('id')->firstOrFail();
    $balances = app(OwnRevenueBudgetBalance::class);

    expect($dossier->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Rejected)
        ->and($transition->from_status)->toBe(OwnRevenueExpenseDossierStatus::PaymentRequested)
        ->and($transition->to_status)->toBe(OwnRevenueExpenseDossierStatus::Rejected)
        ->and($transition->reason)->toBe('La factura no corresponde al concepto autorizado.')
        ->and($transition->actor_id)->toBe($manager->id)
        ->and($balances->committedCents($line->fresh()))->toBe(0)
        ->and($balances->availableCents($line->fresh()))->toBe(10_000);

    $this->actingAs($manager);
    $viewData = app(OwnRevenueExecutionViewData::class)->forBudget($dossier->budget->fresh());
    expect($viewData['permissions']['reject_expense_dossier'])->toBeTrue()
        ->and($viewData['expense_dossiers'][0]['latest_transition']['to_status'])->toBe('rejected')
        ->and($viewData['expense_dossiers'][0]['latest_transition']['reason'])->toBe('La factura no corresponde al concepto autorizado.')
        ->and($viewData['expense_dossiers'][0]['latest_transition']['actor_name'])->toBe($manager->name);
});

test('paid and terminal dossiers cannot be cancelled or rejected', function () {
    ['dossier' => $dossier, 'manager' => $manager] = paymentRequestedExpenseDossier();
    app(AuthorizeExpensePaymentByFinance::class)->handle($dossier, $manager, 'AF-TERMINAL-001');
    app(AuthorizeExpensePaymentByBudgetOffice::class)->handle($dossier, $manager, 'AP-TERMINAL-001');
    app(MarkExpenseDossierPaid::class)->handle($dossier, $manager, 'PAGO-TERMINAL-001');

    expect(fn () => app(CancelExpenseDossier::class)->handle($dossier, $manager, 'Intento de cancelación posterior al pago.'))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RejectExpenseDossier::class)->handle($dossier, $manager, 'Intento de rechazo posterior al pago.'))
        ->toThrow(ValidationException::class);
});

test('expense dossier cancellation and rejection endpoints validate the reason and permissions', function () {
    ['budget' => $budget, 'line' => $line, 'manager' => $manager, 'assistant' => $assistant] = expenseDossierFixture();
    $cancelled = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());

    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.cancel', [$budget, $cancelled]), [
            'reason' => 'Breve',
        ])
        ->assertSessionHasErrors('reason');
    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.cancel', [$budget, $cancelled]), [
            'reason' => 'La necesidad fue atendida por otra vía institucional.',
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget));
    expect($cancelled->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Cancelled);

    $rejected = app(CreateOwnRevenueExpenseDossier::class)->handle($budget, $line, $assistant, expenseDossierData());
    app(RequestExpenseSufficiency::class)->handle($rejected, $assistant);
    $this->actingAs($assistant)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.reject', [$budget, $rejected]), [
            'reason' => 'La solicitud no cumple con los requisitos de revisión.',
        ])
        ->assertForbidden();
    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.execution.expense-dossiers.reject', [$budget, $rejected]), [
            'reason' => 'La solicitud no cumple con los requisitos de revisión.',
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.execution.show', $budget));
    expect($rejected->fresh()->status)->toBe(OwnRevenueExpenseDossierStatus::Rejected);
});

test('execution workspace exposes cancellation and rejection controls with their audited reason', function () {
    $source = file_get_contents(resource_path('js/pages/finance/own-revenue/execution/show.tsx'));

    expect($source)
        ->toContain('expenseDossierRoutes.cancel')
        ->toContain('expenseDossierRoutes.reject')
        ->toContain('latest_transition.reason')
        ->toContain('Cancelar expediente')
        ->toContain('Rechazar expediente');
});
