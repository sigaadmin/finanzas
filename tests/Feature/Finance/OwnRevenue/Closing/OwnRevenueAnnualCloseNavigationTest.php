<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\Planning\OwnRevenueInitialBudget;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function annualCloseNavigationUser(UserRole $role): User
{
    $email = 'annual-close-navigation-'.$role->value.'-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create([
        'email' => $email,
        'role' => $role,
        'is_active' => true,
    ]);

    return User::factory()->create(['email' => $email]);
}

function annualCloseNavigationBudget(): OwnRevenueBudget
{
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::InExecution,
        'fiscal_year' => 2026,
    ]);
    $initialBudget = OwnRevenueInitialBudget::factory()->create([
        'own_revenue_budget_id' => $budget->id,
    ]);
    OwnRevenueModifiedBudgetLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_initial_budget_id' => $initialBudget->id,
        'initial_amount_cents' => 100_000,
    ]);

    return $budget;
}

test('financial roles can review annual close while only administrators may confirm it', function (UserRole $role, bool $canClose) {
    $this->withoutVite();
    $budget = annualCloseNavigationBudget();
    $user = annualCloseNavigationUser($role);

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.annual-close.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/annual-close/show', false)
            ->where('budget.id', $budget->id)
            ->where('review.confirmation_phrase', 'CERRAR 2026')
            ->where('review.eligible', true)
            ->where('permissions.close', $canClose));
})->with([
    'manager' => [UserRole::FinanceManager, true],
    'assistant' => [UserRole::FinanceAssistant, false],
    'auditor' => [UserRole::FinanceAuditor, false],
]);

test('annual close endpoint validates the exact phrase and note', function (array $payload, string $error) {
    $budget = annualCloseNavigationBudget();
    $manager = annualCloseNavigationUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->from(route('finance.own-revenue.budgets.annual-close.show', $budget))
        ->post(route('finance.own-revenue.budgets.annual-close.store', $budget), $payload)
        ->assertRedirect(route('finance.own-revenue.budgets.annual-close.show', $budget))
        ->assertSessionHasErrors($error);

    expect($budget->refresh()->status)->toBe(OwnRevenueBudgetStatus::InExecution)
        ->and($budget->annualClosure)->toBeNull();
})->with([
    'wrong phrase' => [[
        'confirmation' => 'CERRAR',
        'note' => 'Cierre revisado y conciliado.',
    ], 'confirmation'],
    'short note' => [[
        'confirmation' => 'CERRAR 2026',
        'note' => 'Corta',
    ], 'note'],
]);

test('an administrator closes from the endpoint and then consults the immutable act', function () {
    $this->withoutVite();
    $budget = annualCloseNavigationBudget();
    $manager = annualCloseNavigationUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.annual-close.store', $budget), [
            'confirmation' => 'CERRAR 2026',
            'note' => 'Cierre revisado y conciliado.',
        ])
        ->assertRedirect(route('finance.own-revenue.budgets.annual-close.show', $budget))
        ->assertSessionHas('success');

    $this->get(route('finance.own-revenue.budgets.annual-close.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budget.status', OwnRevenueBudgetStatus::Closed->value)
            ->where('closure.note', 'Cierre revisado y conciliado.')
            ->where('closure.closed_by.name', $manager->name)
            ->where('permissions.close', false));
});

test('read only roles cannot submit annual close', function (UserRole $role) {
    $budget = annualCloseNavigationBudget();
    $user = annualCloseNavigationUser($role);

    $this->actingAs($user)
        ->post(route('finance.own-revenue.budgets.annual-close.store', $budget), [
            'confirmation' => 'CERRAR 2026',
            'note' => 'Cierre revisado y conciliado.',
        ])
        ->assertForbidden();
})->with([
    'assistant' => UserRole::FinanceAssistant,
    'auditor' => UserRole::FinanceAuditor,
]);

test('annual budget dashboard exposes close access and existing act metadata', function () {
    $this->withoutVite();
    $budget = annualCloseNavigationBudget();
    $manager = annualCloseNavigationUser(UserRole::FinanceManager);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.closeAnnualBudget', true)
            ->where('budget.annual_closure', null));
});
