<?php

use App\Actions\Finance\OwnRevenue\Fuel\OpenOwnRevenueFuelFund;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function fuelFundUser(UserRole $role): User
{
    $email = 'fuel-fund-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array{manager: User, assistant: User, line: OwnRevenueModifiedBudgetLine, dossier: OwnRevenueExpenseDossier} */
function paidFuelDossier(): array
{
    $manager = fuelFundUser(UserRole::FinanceManager);
    $assistant = fuelFundUser(UserRole::FinanceAssistant);
    $line = OwnRevenueModifiedBudgetLine::factory()->create([
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'specific_item_code' => '26101',
        'specific_item_name' => 'Combustibles, lubricantes y aditivos',
        'month' => 4,
        'initial_amount_cents' => 100_000,
    ]);
    $dossier = OwnRevenueExpenseDossier::factory()->create([
        'own_revenue_budget_id' => $line->own_revenue_budget_id,
        'own_revenue_modified_budget_line_id' => $line->id,
        'status' => OwnRevenueExpenseDossierStatus::Paid,
        'amount_cents' => 80_000,
        'requested_by' => $assistant->id,
        'paid_by' => $manager->id,
        'paid_at' => now(),
    ]);

    return compact('manager', 'assistant', 'line', 'dossier');
}

test('a manager opens the independent fuel fund from the paid April fuel dossier', function () {
    ['manager' => $manager, 'dossier' => $dossier] = paidFuelDossier();

    $fund = app(OpenOwnRevenueFuelFund::class)->handle($dossier, $manager, 78_500);

    expect($fund->budget->is($dossier->budget))->toBeTrue()
        ->and($fund->sourceDossier->is($dossier))->toBeTrue()
        ->and($fund->acquired_amount_cents)->toBe(78_500)
        ->and($fund->openedBy->is($manager))->toBeTrue()
        ->and($fund->opened_at)->not->toBeNull();
});

test('the fund can only be opened once by an administrator from a paid April fuel dossier', function () {
    ['manager' => $manager, 'assistant' => $assistant, 'dossier' => $dossier] = paidFuelDossier();

    expect(fn () => app(OpenOwnRevenueFuelFund::class)->handle($dossier, $assistant, 78_500))
        ->toThrow(AuthorizationException::class);

    foreach ([
        'unpaid' => ['status' => OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized],
        'wrong item' => ['specific_item_code' => '21101'],
        'wrong month' => ['month' => 5],
    ] as $change) {
        $dossier->update(['status' => OwnRevenueExpenseDossierStatus::Paid]);
        $dossier->budgetLine->update(array_diff_key($change, ['status' => true]));
        if (isset($change['status'])) {
            $dossier->update(['status' => $change['status']]);
        }

        expect(fn () => app(OpenOwnRevenueFuelFund::class)->handle($dossier->fresh(), $manager, 78_500))
            ->toThrow(ValidationException::class);
    }

    $dossier->budgetLine->update(['specific_item_code' => '26101', 'month' => 4]);
    $dossier->update(['status' => OwnRevenueExpenseDossierStatus::Paid]);
    app(OpenOwnRevenueFuelFund::class)->handle($dossier->fresh(), $manager, 78_500);

    expect(fn () => app(OpenOwnRevenueFuelFund::class)->handle($dossier->fresh(), $manager, 78_500))
        ->toThrow(ValidationException::class);
});
