<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function annualAuditNavigationUser(UserRole $role): User
{
    $email = 'annual-audit-'.$role->value.'-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create([
        'email' => $email,
        'role' => $role,
        'is_active' => true,
    ]);

    return User::factory()->create(['email' => $email]);
}

test('financial roles consult the annual audit as a read only page', function (UserRole $role) {
    $this->withoutVite();
    $budget = OwnRevenueBudget::factory()->create();
    $user = annualAuditNavigationUser($role);

    $this->actingAs($user)
        ->get(route('finance.own-revenue.budgets.audit.index', [
            'budget' => $budget,
            'type' => 'configuration',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('finance/own-revenue/audit/index', false)
            ->where('budget.id', $budget->id)
            ->where('timeline.applied_type', 'configuration')
            ->where('timeline.events.0.type', 'configuration')
            ->where('permissions.read_only', true));
})->with([
    'manager' => UserRole::FinanceManager,
    'assistant' => UserRole::FinanceAssistant,
    'auditor' => UserRole::FinanceAuditor,
]);

test('users without financial access cannot consult annual audit', function () {
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('finance.own-revenue.budgets.audit.index', $budget))
        ->assertForbidden();
});
