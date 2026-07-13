<?php

use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\User;
use Illuminate\Database\QueryException;

test('own revenue enums cover the complete annual lifecycle', function () {
    expect(OwnRevenueBudgetStatus::cases())->toEqual([
        OwnRevenueBudgetStatus::Draft,
        OwnRevenueBudgetStatus::ProposalCalculated,
        OwnRevenueBudgetStatus::ProposalAdjusted,
        OwnRevenueBudgetStatus::InitialAuthorized,
        OwnRevenueBudgetStatus::InExecution,
        OwnRevenueBudgetStatus::Closed,
    ])->and(array_column(OwnRevenueBudgetStatus::cases(), 'value'))->toBe([
        'draft',
        'proposal_calculated',
        'proposal_adjusted',
        'initial_authorized',
        'in_execution',
        'closed',
    ])->and(AnnualValueStatus::cases())->toEqual([
        AnnualValueStatus::PendingReview,
        AnnualValueStatus::Provisional,
        AnnualValueStatus::Final,
    ])->and(array_column(AnnualValueStatus::cases(), 'value'))->toBe([
        'pending_review',
        'provisional',
        'final',
    ])->and(CogCatalogStatus::cases())->toEqual([
        CogCatalogStatus::PendingConfirmation,
        CogCatalogStatus::Confirmed,
    ])->and(array_column(CogCatalogStatus::cases(), 'value'))->toBe([
        'pending_confirmation',
        'confirmed',
    ]);
});

test('annual budget casts statuses and decimal values while preserving fixed defaults', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'status' => OwnRevenueBudgetStatus::ProposalCalculated,
        'uma_value' => '113.1400',
        'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '24.5000',
        'fuel_price_status' => AnnualValueStatus::Provisional,
        'cog_status' => CogCatalogStatus::Confirmed,
    ]);

    expect($budget->status)->toBe(OwnRevenueBudgetStatus::ProposalCalculated)
        ->and($budget->uma_status)->toBe(AnnualValueStatus::Final)
        ->and($budget->fuel_price_status)->toBe(AnnualValueStatus::Provisional)
        ->and($budget->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($budget->uma_value)->toBeString()->toBe('113.1400')
        ->and($budget->fuel_price_per_liter)->toBeString()->toBe('24.5000')
        ->and($budget->region_code)->toBe('02-001')
        ->and($budget->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($budget->fuel_budget_month)->toBe(4);

    $budget->update([
        'uma_value' => null,
        'fuel_price_per_liter' => null,
    ]);

    expect($budget->refresh()->uma_value)->toBeNull()
        ->and($budget->fuel_price_per_liter)->toBeNull();
});

test('annual budget applies fixed region and fuel month defaults without factory values', function () {
    $budget = OwnRevenueBudget::query()->create([
        'created_by' => User::factory()->create()->getKey(),
        'fiscal_year' => 2030,
        'institution_name' => 'Centro Regional de Educación Normal',
        'responsible_unit_code' => '2112102003',
        'responsible_unit_name' => 'Dirección del Plantel',
        'budget_program_code' => 'E062',
        'budget_program_name' => 'Formación Inicial Docente',
        'component_code' => 'C01',
        'component_name' => 'Servicios de formación docente proporcionados',
        'official_activity_code' => 'A01',
        'official_activity_name' => 'Operación de los programas de formación docente',
    ])->refresh();

    expect($budget->region_code)->toBe('02-001')
        ->and($budget->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($budget->fuel_budget_month)->toBe(4);
});

test('annual decimal values normalize to four decimal places', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'uma_value' => '123.45',
        'fuel_price_per_liter' => '123.45',
    ]);

    expect($budget->uma_value)->toBeString()->toBe('123.4500')
        ->and($budget->fuel_price_per_liter)->toBeString()->toBe('123.4500');
});

test('fiscal year is unique across annual own revenue budgets', function () {
    OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);

    expect(fn () => OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]))
        ->toThrow(QueryException::class);
});

test('annual budget exposes its users activities and signatories', function () {
    $creator = User::factory()->create();
    $confirmer = User::factory()->create();
    $budget = OwnRevenueBudget::factory()
        ->for($creator, 'createdBy')
        ->for($confirmer, 'cogConfirmedBy')
        ->create(['cog_confirmed_at' => now()]);
    $activity = OwnRevenueActivity::factory()->for($budget, 'budget')->create();
    $signatory = OwnRevenueSignatory::factory()->for($budget, 'budget')->create();

    expect($budget->createdBy->is($creator))->toBeTrue()
        ->and($budget->cogConfirmedBy->is($confirmer))->toBeTrue()
        ->and($budget->cog_confirmed_at)->not->toBeNull()
        ->and($budget->activities->modelKeys())->toBe([$activity->getKey()])
        ->and($budget->signatories->modelKeys())->toBe([$signatory->getKey()])
        ->and($activity->budget->is($budget))->toBeTrue()
        ->and($signatory->budget->is($budget))->toBeTrue();
});

test('own revenue factories create meaningful valid annual data', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $activity = OwnRevenueActivity::factory()->create();
    $signatory = OwnRevenueSignatory::factory()->create();

    expect($budget->createdBy)->toBeInstanceOf(User::class)
        ->and($budget->fiscal_year)->toBeGreaterThanOrEqual(2026)
        ->and($budget->institution_name)->not->toBeEmpty()
        ->and($budget->responsible_unit_code)->not->toBeEmpty()
        ->and($budget->budget_program_code)->not->toBeEmpty()
        ->and($budget->component_code)->not->toBeEmpty()
        ->and($budget->official_activity_code)->not->toBeEmpty()
        ->and($budget->uma_value)->toBeString()->toMatch('/^\d+\.\d{4}$/')
        ->and($budget->fuel_price_per_liter)->toBeString()->toMatch('/^\d+\.\d{4}$/')
        ->and($activity->budget)->toBeInstanceOf(OwnRevenueBudget::class)
        ->and($activity->code)->not->toBeEmpty()
        ->and($activity->name)->not->toBeEmpty()
        ->and($signatory->budget)->toBeInstanceOf(OwnRevenueBudget::class)
        ->and($signatory->role_key)->not->toBeEmpty()
        ->and($signatory->name)->not->toBeEmpty()
        ->and($signatory->position)->not->toBeEmpty();
});
