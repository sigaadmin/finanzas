<?php

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;

function ownRevenueImportUser(UserRole $role, bool $isActive = true): User
{
    $email = sprintf('import-%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());

    AuthorizedAccess::create([
        'email' => $email,
        'role' => $role,
        'is_active' => $isActive,
    ]);

    return User::factory()->create(['email' => $email]);
}

it('separates import administration from consultation', function (UserRole $role, bool $view, bool $manage) {
    $user = ownRevenueImportUser($role);
    $budget = OwnRevenueBudget::factory()->create();

    expect($user->can('viewImports', $budget))->toBe($view)
        ->and($user->can('manageImports', $budget))->toBe($manage)
        ->and($user->can('confirmImports', $budget))->toBe($manage);
})->with([
    'owner' => [UserRole::Owner, true, true],
    'admin' => [UserRole::Admin, true, true],
    'manager' => [UserRole::FinanceManager, true, true],
    'assistant' => [UserRole::FinanceAssistant, true, false],
    'auditor' => [UserRole::FinanceAuditor, true, false],
]);

it('denies import access without active authorized finance access', function (string $accessState) {
    $user = match ($accessState) {
        'inactive' => ownRevenueImportUser(UserRole::FinanceManager, false),
        'missing' => User::factory()->create(),
    };
    $budget = OwnRevenueBudget::factory()->create();

    expect($user->can('viewImports', $budget))->toBeFalse()
        ->and($user->can('manageImports', $budget))->toBeFalse()
        ->and($user->can('confirmImports', $budget))->toBeFalse();
})->with(['inactive', 'missing']);

it('only manages and confirms imports while the budget is a draft', function (OwnRevenueBudgetStatus $status) {
    foreach ([UserRole::Owner, UserRole::Admin, UserRole::FinanceManager] as $role) {
        $user = ownRevenueImportUser($role);
        $budget = OwnRevenueBudget::factory()->create(['status' => $status]);

        expect($user->can('viewImports', $budget))->toBeTrue()
            ->and($user->can('manageImports', $budget))->toBeFalse()
            ->and($user->can('confirmImports', $budget))->toBeFalse();
    }
})->with([
    OwnRevenueBudgetStatus::ProposalCalculated,
    OwnRevenueBudgetStatus::ProposalAdjusted,
    OwnRevenueBudgetStatus::InitialAuthorized,
    OwnRevenueBudgetStatus::InExecution,
    OwnRevenueBudgetStatus::Closed,
]);
