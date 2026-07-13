<?php

use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Policies\Finance\OwnRevenue\OwnRevenueBudgetPolicy;
use Illuminate\Support\Facades\Gate;

function ownRevenueBudgetUser(UserRole $role, bool $isActive = true): User
{
    $email = sprintf('%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());

    AuthorizedAccess::create([
        'email' => $email,
        'role' => $role,
        'is_active' => $isActive,
    ]);

    return User::factory()->create(['email' => $email]);
}

function assertOwnRevenueBudgetPolicyPermissions(
    OwnRevenueBudgetPolicy $policy,
    User $user,
    OwnRevenueBudget $budget,
    bool $canView,
    bool $canAdministrate,
): void {
    expect($policy->viewAny($user))->toBe($canView)
        ->and($policy->view($user, $budget))->toBe($canView)
        ->and($policy->create($user))->toBe($canAdministrate)
        ->and($policy->updateSettings($user, $budget))->toBe($canAdministrate)
        ->and($policy->copy($user, $budget))->toBe($canAdministrate)
        ->and($policy->confirmCog($user, $budget))->toBe($canAdministrate);
}

test('Laravel discovers the nested own revenue budget policy', function () {
    expect(Gate::getPolicyFor(OwnRevenueBudget::class))
        ->toBeInstanceOf(OwnRevenueBudgetPolicy::class);
});

test('administrative roles have all annual budget permissions through the policy', function (UserRole $role) {
    $user = ownRevenueBudgetUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    $policy = app(OwnRevenueBudgetPolicy::class);

    assertOwnRevenueBudgetPolicyPermissions($policy, $user, $budget, true, true);
})->with([
    'owner' => UserRole::Owner,
    'admin' => UserRole::Admin,
    'finance manager' => UserRole::FinanceManager,
]);

test('admin and finance manager permissions are resolved through the discovered policy', function (UserRole $role) {
    $user = ownRevenueBudgetUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', OwnRevenueBudget::class))->toBeTrue()
        ->and($gate->allows('view', $budget))->toBeTrue()
        ->and($gate->allows('create', OwnRevenueBudget::class))->toBeTrue()
        ->and($gate->allows('updateSettings', $budget))->toBeTrue()
        ->and($gate->allows('copy', $budget))->toBeTrue()
        ->and($gate->allows('confirmCog', $budget))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'finance manager' => UserRole::FinanceManager,
]);

test('read only finance roles can view budgets but cannot administrate them', function (UserRole $role) {
    $user = ownRevenueBudgetUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    $policy = app(OwnRevenueBudgetPolicy::class);
    $gate = Gate::forUser($user);

    assertOwnRevenueBudgetPolicyPermissions($policy, $user, $budget, true, false);

    expect($gate->allows('viewAny', OwnRevenueBudget::class))->toBeTrue()
        ->and($gate->allows('view', $budget))->toBeTrue()
        ->and($gate->allows('create', OwnRevenueBudget::class))->toBeFalse()
        ->and($gate->allows('updateSettings', $budget))->toBeFalse()
        ->and($gate->allows('copy', $budget))->toBeFalse()
        ->and($gate->allows('confirmCog', $budget))->toBeFalse();
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);

test('users without active authorized finance access cannot view or administrate budgets', function (string $accessState) {
    $user = match ($accessState) {
        'public' => ownRevenueBudgetUser(UserRole::Public),
        'inactive' => ownRevenueBudgetUser(UserRole::FinanceManager, false),
        'missing' => User::factory()->create(),
    };
    $budget = OwnRevenueBudget::factory()->create();
    $policy = app(OwnRevenueBudgetPolicy::class);
    $gate = Gate::forUser($user);

    assertOwnRevenueBudgetPolicyPermissions($policy, $user, $budget, false, false);

    expect($gate->allows('viewAny', OwnRevenueBudget::class))->toBeFalse()
        ->and($gate->allows('view', $budget))->toBeFalse()
        ->and($gate->allows('create', OwnRevenueBudget::class))->toBeFalse()
        ->and($gate->allows('updateSettings', $budget))->toBeFalse()
        ->and($gate->allows('copy', $budget))->toBeFalse()
        ->and($gate->allows('confirmCog', $budget))->toBeFalse();
})->with([
    'public role' => 'public',
    'inactive finance manager access' => 'inactive',
    'missing authorized access' => 'missing',
]);

test('annual budgets cannot be deleted restored or force deleted', function (UserRole $role) {
    $user = ownRevenueBudgetUser($role);
    $budget = OwnRevenueBudget::factory()->create();
    $policy = app(OwnRevenueBudgetPolicy::class);

    expect($policy->delete($user, $budget))->toBeFalse()
        ->and($policy->restore($user, $budget))->toBeFalse()
        ->and($policy->forceDelete($user, $budget))->toBeFalse();
})->with([
    'owner' => UserRole::Owner,
    'admin' => UserRole::Admin,
    'finance manager' => UserRole::FinanceManager,
]);
