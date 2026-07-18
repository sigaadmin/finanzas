<?php

use App\Actions\Finance\OwnRevenue\Closing\CloseOwnRevenueBudget;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function annualCloseUser(UserRole $role): User
{
    $email = 'annual-close-'.$role->value.'-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create([
        'email' => $email,
        'role' => $role,
        'is_active' => true,
    ]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{budget: OwnRevenueBudget, line: OwnRevenueModifiedBudgetLine} */
function closableAnnualBudget(): array
{
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InExecution,
    ]);
    $initialBudget = OwnRevenueInitialBudget::factory()->create([
        'own_revenue_budget_id' => $budget->id,
    ]);
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'initial_amount_cents' => 100_000,
    ]);

    return compact('budget', 'line');
}

test('a financial administrator closes an eligible budget with an immutable fingerprinted snapshot', function (UserRole $role) {
    ['budget' => $budget] = closableAnnualBudget();
    $user = annualCloseUser($role);

    $closure = app(CloseOwnRevenueBudget::class)->handle(
        $budget,
        $user,
        'Cierre revisado, conciliado y autorizado.',
    );

    expect($budget->refresh()->status)->toBe(OwnRevenueBudgetStatus::Closed)
        ->and($closure->closed_by)->toBe($user->id)
        ->and($closure->fingerprint)->toHaveLength(64)
        ->and(hash('sha256', $closure->canonicalSnapshot()))->toBe($closure->fingerprint)
        ->and($closure->snapshot['balances']['available_amount_cents'])->toBe('100000')
        ->and($budget->annualClosure->is($closure))->toBeTrue();
})->with([
    'owner' => UserRole::Owner,
    'administrator' => UserRole::Admin,
    'finance manager' => UserRole::FinanceManager,
]);

test('assistants and auditors cannot close an annual budget', function (UserRole $role) {
    ['budget' => $budget] = closableAnnualBudget();
    $user = annualCloseUser($role);

    expect(fn () => app(CloseOwnRevenueBudget::class)->handle(
        $budget,
        $user,
        'Cierre revisado, conciliado y autorizado.',
    ))->toThrow(AuthorizationException::class)
        ->and($budget->refresh()->status)->toBe(OwnRevenueBudgetStatus::InExecution)
        ->and($budget->annualClosure)->toBeNull();
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);

test('the close action rechecks blockers and leaves the budget unchanged', function () {
    ['budget' => $budget, 'line' => $line] = closableAnnualBudget();
    $manager = annualCloseUser(UserRole::FinanceManager);
    OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::Draft,
    ]);

    try {
        app(CloseOwnRevenueBudget::class)->handle(
            $budget,
            $manager,
            'Cierre revisado, conciliado y autorizado.',
        );
    } catch (ValidationException $exception) {
        expect($exception->errors()['closure'])->toBe([
            'Actualiza la revisión: Hay 1 expediente que todavía requiere concluirse.',
        ]);
    }

    expect($budget->refresh()->status)->toBe(OwnRevenueBudgetStatus::InExecution)
        ->and($budget->annualClosure)->toBeNull();
});

test('an annual close cannot be repeated or reopened', function () {
    ['budget' => $budget] = closableAnnualBudget();
    $manager = annualCloseUser(UserRole::FinanceManager);
    $action = app(CloseOwnRevenueBudget::class);
    $closure = $action->handle($budget, $manager, 'Cierre revisado, conciliado y autorizado.');
    $fingerprint = $closure->fingerprint;
    $closedAt = $closure->closed_at;

    expect(fn () => $action->handle(
        $budget->refresh(),
        $manager,
        'Segundo intento de cierre no permitido.',
    ))->toThrow(AuthorizationException::class)
        ->and($budget->annualClosure()->count())->toBe(1)
        ->and($closure->refresh()->fingerprint)->toBe($fingerprint)
        ->and($closure->closed_at->equalTo($closedAt))->toBeTrue();
});

test('budgets outside execution cannot be closed', function (OwnRevenueBudgetStatus $status) {
    ['budget' => $budget] = closableAnnualBudget();
    $budget->update(['status' => $status]);
    $manager = annualCloseUser(UserRole::FinanceManager);

    expect(fn () => app(CloseOwnRevenueBudget::class)->handle(
        $budget,
        $manager,
        'Cierre revisado, conciliado y autorizado.',
    ))->toThrow(AuthorizationException::class);
})->with([
    'draft' => OwnRevenueBudgetStatus::Draft,
    'proposal adjusted' => OwnRevenueBudgetStatus::ProposalAdjusted,
    'closed' => OwnRevenueBudgetStatus::Closed,
]);
