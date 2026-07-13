<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function ownRevenueNavigationUser(UserRole $role): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::query()->create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

test('manager navigates annual budget pages with the expected inertia contracts', function () {
    $manager = ownRevenueNavigationUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2028]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/index')
            ->where('budgets.0.id', $budget->id)
            ->where('permissions.create', true));

    $this->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/show')
            ->where('budget.settings.uma_value', '113.1400')
            ->where('budget.settings.fuel_price_per_liter', '24.5000')
            ->where('permissions.updateSettings', true)
            ->where('permissions.copy', true)
            ->where('permissions.confirmCog', true));
});

test('finance assistant receives readonly annual budget permissions', function () {
    $assistant = ownRevenueNavigationUser(UserRole::FinanceAssistant);
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($assistant)
        ->get(route('finance.own-revenue.budgets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/index')
            ->where('permissions.create', false));

    $this->get(route('finance.own-revenue.budgets.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/show')
            ->where('permissions.updateSettings', false)
            ->where('permissions.copy', false)
            ->where('permissions.confirmCog', false));
});

test('create page provides source budgets for the copy workflow', function () {
    $manager = ownRevenueNavigationUser(UserRole::FinanceManager);
    $source = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);

    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/own-revenue/budgets/create')
            ->where('sourceBudgets.0.id', $source->id)
            ->where('sourceBudgets.0.fiscal_year', 2027)
            ->where('sourceBudgets.0.status', 'draft')
            ->where('permissions.create', true));
});

test('sidebar exposes the annual own revenue budget destination through wayfinder', function () {
    $sidebar = file_get_contents(resource_path('js/components/app-sidebar.tsx'));

    expect($sidebar)
        ->toContain('Presupuesto de Ingresos Propios')
        ->toContain('@/routes/finance/own-revenue/budgets')
        ->toContain('ownRevenueBudgets.index()');
});

test('show dashboard source includes complete readonly configuration audit and copy preselection', function () {
    $showPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/show.tsx'));

    expect($showPage)
        ->toContain('Fotografía institucional del ejercicio')
        ->toContain('Unidad responsable')
        ->toContain('Componente presupuestario')
        ->toContain('Actividad oficial')
        ->toContain('Creado')
        ->toContain('Última actualización')
        ->toContain('href={create({')
        ->toContain('source_budget_id: budget.id');
});

test('create page source consumes a valid inertia url source budget without window', function () {
    $createPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/create.tsx'));

    expect($createPage)
        ->toContain('usePage')
        ->toContain('requestedSourceBudgetId')
        ->toContain('preselectedSourceBudget')
        ->toContain('sourceBudgets.some')
        ->not->toContain('window.location');
});

test('annual frontend source protects draft editing copy mode and deterministic dates', function () {
    $createPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/create.tsx'));
    $showPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/show.tsx'));
    $settingsForm = file_get_contents(resource_path('js/components/finance/own-revenue/annual-settings-form.tsx'));

    expect($showPage)
        ->toContain("permissions.updateSettings && budget.status === 'draft'")
        ->toContain("budget.status !== 'draft'")
        ->toContain("timeZone: 'America/Cancun'")
        ->toContain('cogForm.errors.catalog')
        ->toContain('cogForm.errors.confirmed_by')
        ->toContain('scope="col"')
        ->and($createPage)
        ->toContain('setEstimatedIncomeError(undefined)')
        ->toContain("mode === 'blank' && estimatedIncomeError")
        ->toContain("mode === 'blank' &&")
        ->toContain('Boolean(estimatedIncomeError)')
        ->and($settingsForm)
        ->toContain('form.errors.budget')
        ->toContain('clientKey')
        ->toContain('clearErrors(')
        ->toContain('aria-describedby')
        ->not->toContain('key={index}')
        ->not->toContain('Number(`${whole}')
        ->not->toContain('parseInt(');
});

test('estimated income frontend contracts use decimal strings throughout', function () {
    $types = file_get_contents(resource_path('js/types/finance-own-revenue.ts'));
    $createPage = file_get_contents(resource_path('js/pages/finance/own-revenue/budgets/create.tsx'));
    $settingsForm = file_get_contents(resource_path('js/components/finance/own-revenue/annual-settings-form.tsx'));

    expect($types)
        ->toContain('estimated_income_cents: string | null;')
        ->not->toContain('estimated_income_cents: number | null;')
        ->and($createPage)
        ->toContain('estimated_income_cents: string | null;')
        ->not->toContain('.estimated_income_cents.toLocaleString(')
        ->and($settingsForm)
        ->toContain('pesosToCents(pesos: string): string | null')
        ->toContain('centsToPesos(cents: string | null): string');
});
