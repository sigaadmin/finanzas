<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function internalReportUser(UserRole $role): User
{
    $email = 'internal-report-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create([
        'email' => $email,
        'role' => $role,
        'is_active' => true,
    ]);

    return User::factory()->create(['email' => $email]);
}

test('authorized finance roles can consult internal reports', function (UserRole $role) {
    $this->withoutVite();
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'chapter_code' => '2000',
        'specific_item_code' => '21101',
        'month' => 5,
    ]);
    $user = internalReportUser($role);

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.reports.show', [
            'budget' => $line->budget,
            'chapter_code' => '2000',
            'specific_item_code' => '21101',
            'month' => 5,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/reports/show', false)
            ->where('budget.id', $line->own_revenue_budget_id)
            ->where('filters.applied.month', 5)
            ->where('permissions.read_only', true));
})->with([
    'manager' => UserRole::FinanceManager,
    'assistant' => UserRole::FinanceAssistant,
    'auditor' => UserRole::FinanceAuditor,
]);

test('users without financial access cannot consult internal reports', function () {
    $line = OwnRevenueModifiedBudgetLine::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('finance.own-revenue.budgets.reports.show', $line->budget))
        ->assertForbidden();
});
