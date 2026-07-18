<?php

use App\Actions\Finance\OwnRevenue\Fuel\ConfirmOwnRevenueFuelCommission;
use App\Actions\Finance\OwnRevenue\Fuel\CreateOwnRevenueFuelCommission;
use App\Enums\Finance\OwnRevenue\OwnRevenueFuelCommissionStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function fuelCommissionUser(UserRole $role): User
{
    $email = 'fuel-commission-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @return array<string, mixed> */
function fuelCommissionData(int $amountCents = 25_000, int $fiscalYear = 2026): array
{
    return [
        'own_revenue_proposal_fuel_need_id' => null,
        'commission_date' => $fiscalYear.'-05-15',
        'reason' => 'Traslado para actividad académica',
        'route_description' => 'Felipe Carrillo Puerto - Cancún - Felipe Carrillo Puerto',
        'vehicle_description' => 'Nissan NP300',
        'kilometers' => '220.0000',
        'liters' => '10.0000',
        'amount_cents' => $amountCents,
        'is_extraordinary' => false,
        'extraordinary_justification' => null,
    ];
}

test('an operator records and confirms a commission with calculated effective price and balance', function () {
    $assistant = fuelCommissionUser(UserRole::FinanceAssistant);
    $fund = OwnRevenueFuelFund::factory()->create(['acquired_amount_cents' => 100_000]);

    $commission = app(CreateOwnRevenueFuelCommission::class)->handle(
        $fund,
        $assistant,
        fuelCommissionData(fiscalYear: $fund->budget->fiscal_year),
    );

    expect($commission->status)->toBe(OwnRevenueFuelCommissionStatus::Pending)
        ->and($commission->effective_price_per_liter)->toBe('25.0000')
        ->and($commission->balance_after_cents)->toBeNull();

    app(ConfirmOwnRevenueFuelCommission::class)->handle($commission, $assistant);

    expect($commission->fresh()->status)->toBe(OwnRevenueFuelCommissionStatus::Confirmed)
        ->and($commission->fresh()->balance_after_cents)->toBe(75_000)
        ->and($commission->fresh()->confirmed_by)->toBe($assistant->id);
});

test('an extraordinary commission requires justification and insufficient funds remain pending', function () {
    $manager = fuelCommissionUser(UserRole::FinanceManager);
    $fund = OwnRevenueFuelFund::factory()->create(['acquired_amount_cents' => 20_000]);
    $data = [...fuelCommissionData(25_000, $fund->budget->fiscal_year), 'is_extraordinary' => true];

    expect(fn () => app(CreateOwnRevenueFuelCommission::class)->handle($fund, $manager, $data))
        ->toThrow(ValidationException::class);

    $commission = app(CreateOwnRevenueFuelCommission::class)->handle($fund, $manager, [
        ...$data,
        'extraordinary_justification' => 'Comisión no contemplada necesaria para atender una gestión urgente.',
    ]);

    expect(fn () => app(ConfirmOwnRevenueFuelCommission::class)->handle($commission, $manager))
        ->toThrow(ValidationException::class);
    expect($commission->fresh()->status)->toBe(OwnRevenueFuelCommissionStatus::Pending)
        ->and($commission->fresh()->balance_after_cents)->toBeNull();
});
