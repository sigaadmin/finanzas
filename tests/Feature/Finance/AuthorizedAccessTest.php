<?php

use App\Enums\Finance\PaymentProcedureStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\PaymentProcedure;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Gate;

test('authorized access normalizes email and stores the assigned role', function () {
    $access = AuthorizedAccess::create([
        'email' => ' Responsable.Finanzas@CRENFCP.edu.mx ',
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);

    expect($access->email)->toBe('responsable.finanzas@crenfcp.edu.mx')
        ->and($access->role)->toBe(UserRole::FinanceManager)
        ->and($access->is_active)->toBeTrue()
        ->and($access->last_used_at)->toBeNull();
});

test('database seeder guarantees the institutional owner access', function () {
    $this->seed(DatabaseSeeder::class);

    $access = AuthorizedAccess::query()
        ->where('email', 'administrador.siga@crenfcp.edu.mx')
        ->first();

    expect($access)->not->toBeNull()
        ->and($access->role)->toBe(UserRole::Owner)
        ->and($access->is_active)->toBeTrue();
});

test('users expose explicit role helpers based on their authorized access', function () {
    AuthorizedAccess::create([
        'email' => 'auxiliar.finanzas@crenfcp.edu.mx',
        'role' => UserRole::FinanceAssistant,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email' => 'auxiliar.finanzas@crenfcp.edu.mx',
    ]);

    expect($user->authorizedAccess)->not->toBeNull()
        ->and($user->hasRole(UserRole::FinanceAssistant))->toBeTrue()
        ->and($user->isFinanceAssistant())->toBeTrue()
        ->and($user->isFinanceManager())->toBeFalse()
        ->and($user->isOwner())->toBeFalse();
});

test('inactive or missing authorized access cannot operate finance features', function () {
    AuthorizedAccess::create([
        'email' => 'inactivo@crenfcp.edu.mx',
        'role' => UserRole::FinanceManager,
        'is_active' => false,
    ]);

    $inactiveUser = User::factory()->create([
        'email' => 'inactivo@crenfcp.edu.mx',
    ]);

    $missingAccessUser = User::factory()->create([
        'email' => 'sin.acceso@crenfcp.edu.mx',
    ]);

    expect($inactiveUser->canOperateFinance())->toBeFalse()
        ->and($missingAccessUser->canOperateFinance())->toBeFalse();
});

test('owner is allowed to perform every administrative action', function () {
    AuthorizedAccess::create([
        'email' => 'owner@crenfcp.edu.mx',
        'role' => UserRole::Owner,
        'is_active' => true,
    ]);

    $owner = User::factory()->create([
        'email' => 'owner@crenfcp.edu.mx',
    ]);

    $procedure = PaymentProcedure::factory()->create([
        'status' => PaymentProcedureStatus::Paid,
    ]);

    expect(Gate::forUser($owner)->allows('delete', $procedure))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $procedure))->toBeTrue();
});
