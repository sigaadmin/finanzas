<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function fuelNavigationUser(UserRole $role): User
{
    $email = 'fuel-navigation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

test('the fuel workspace shows the eligible paid dossier and empty fund balances', function () {
    $manager = fuelNavigationUser(UserRole::FinanceManager);
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'specific_item_code' => '26101',
        'specific_item_name' => 'Combustibles, lubricantes y aditivos',
        'month' => 4,
    ]);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $line->own_revenue_budget_id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::Paid,
        'amount_cents' => 80_000,
        'paid_by' => $manager->id,
        'paid_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.fuel.show', $line->budget))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/fuel/show')
            ->where('budget.id', $line->own_revenue_budget_id)
            ->where('fund', null)
            ->where('summary.acquired_amount_cents', '0')
            ->where('summary.available_amount_cents', '0')
            ->where('eligible_dossiers.0.id', $dossier->id)
            ->where('permissions.open_fund', true));
});

test('a manager opens the fund from the workspace while an assistant cannot', function () {
    $manager = fuelNavigationUser(UserRole::FinanceManager);
    $assistant = fuelNavigationUser(UserRole::FinanceAssistant);
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'specific_item_code' => '26101',
        'month' => 4,
    ]);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $line->own_revenue_budget_id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::Paid,
        'paid_by' => $manager->id,
        'paid_at' => now(),
    ]);
    $url = route('finance.own-revenue.budgets.fuel.store', $line->budget);
    $payload = ['source_expense_dossier_id' => $dossier->id, 'acquired_amount_cents' => 78_500];

    $this->actingAs($assistant)->post($url, $payload)->assertForbidden();
    $this->actingAs($manager)->post($url, $payload)
        ->assertRedirect(route('finance.own-revenue.budgets.fuel.show', $line->budget));

    $this->assertDatabaseHas('own_revenue_fuel_funds', [
        'own_revenue_budget_id' => $line->own_revenue_budget_id,
        'source_expense_dossier_id' => $dossier->id,
        'acquired_amount_cents' => 78_500,
    ]);
});

test('the fuel page uses same-window navigation and readable labels', function () {
    $source = file_get_contents(resource_path('js/pages/finance/own-revenue/fuel/show.tsx'));
    $budgetSource = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/show.tsx'));

    expect($source)
        ->toContain('Fondo adquirido')
        ->toContain('Saldo disponible')
        ->toContain('Abrir fondo operativo')
        ->not->toContain('target="_blank"')
        ->and($budgetSource)->toContain('fuel.show(budget.id)');
});
