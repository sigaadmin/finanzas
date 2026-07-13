<?php

use App\Actions\Finance\OwnRevenue\InitializeOwnRevenueBudget;
use App\Actions\Finance\OwnRevenue\UpdateOwnRevenueBudgetSettings;
use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function ownRevenueBudgetData(array $overrides = []): array
{
    return array_replace([
        'fiscal_year' => 2027,
        'institution_name' => 'Centro Regional de Educación Normal',
        'responsible_unit_code' => '2112102003',
        'responsible_unit_name' => 'Dirección del Plantel',
        'budget_program_code' => 'E062',
        'budget_program_name' => 'Formación Inicial Docente',
        'component_code' => 'C01',
        'component_name' => 'Servicios de formación docente proporcionados',
        'official_activity_code' => 'A01',
        'official_activity_name' => 'Operación de los programas de formación docente',
    ], $overrides);
}

test('initialization creates a draft annual budget with protected defaults and ordered activities', function () {
    $creator = User::factory()->create();

    $budget = app(InitializeOwnRevenueBudget::class)->handle($creator, ownRevenueBudgetData([
        'status' => OwnRevenueBudgetStatus::Closed->value,
        'region_code' => 'malicious',
        'region_name' => 'malicious',
        'fuel_budget_month' => 12,
        'cog_status' => CogCatalogStatus::Confirmed->value,
        'created_by' => User::factory()->create()->getKey(),
    ]));

    expect($budget->created_by)->toBe($creator->getKey())
        ->and($budget->status)->toBe(OwnRevenueBudgetStatus::Draft)
        ->and($budget->region_code)->toBe('02-001')
        ->and($budget->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($budget->fuel_budget_month)->toBe(4)
        ->and($budget->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($budget->uma_value)->toBeNull()
        ->and($budget->uma_status)->toBe(AnnualValueStatus::PendingReview)
        ->and($budget->fuel_price_per_liter)->toBeNull()
        ->and($budget->fuel_price_status)->toBe(AnnualValueStatus::PendingReview)
        ->and($budget->activities->map->only(['code', 'name', 'sort_order'])->all())->toBe([
            ['code' => 'A01', 'name' => 'Fomento de la investigación', 'sort_order' => 1],
            ['code' => 'A02', 'name' => 'Profesorado y docencia', 'sort_order' => 2],
            ['code' => 'A03', 'name' => 'Difusión', 'sort_order' => 3],
            ['code' => 'A04', 'name' => 'Gestión', 'sort_order' => 4],
        ])
        ->and($budget->activities)->toHaveCount(4)
        ->and($budget->activities->pluck('code')->unique())->toHaveCount(4);
});

test('initialization accepts positive decimal strings and persists coherent annual value statuses', function () {
    $budget = app(InitializeOwnRevenueBudget::class)->handle(User::factory()->create(), ownRevenueBudgetData([
        'uma_value' => '113.14',
        'uma_status' => AnnualValueStatus::Final->value,
        'fuel_price_per_liter' => '24.5',
        'fuel_price_status' => AnnualValueStatus::Provisional->value,
        'estimated_income_cents' => 1_250_000,
        'cut_percentage' => '5.25',
    ]));

    expect($budget->uma_value)->toBe('113.1400')
        ->and($budget->uma_status)->toBe(AnnualValueStatus::Final)
        ->and($budget->fuel_price_per_liter)->toBe('24.5000')
        ->and($budget->fuel_price_status)->toBe(AnnualValueStatus::Provisional)
        ->and($budget->estimated_income_cents)->toBe(1_250_000)
        ->and($budget->cut_percentage)->toBe('5.25');
});

test('a provided annual value without a status starts as provisional', function () {
    $budget = app(InitializeOwnRevenueBudget::class)->handle(User::factory()->create(), ownRevenueBudgetData([
        'uma_value' => '1',
        'fuel_price_per_liter' => '2.1234',
    ]));

    expect($budget->uma_status)->toBe(AnnualValueStatus::Provisional)
        ->and($budget->fuel_price_status)->toBe(AnnualValueStatus::Provisional);
});

test('initialization rejects invalid decimal values without leaving partial records', function (string $field, mixed $value) {
    expect(fn () => app(InitializeOwnRevenueBudget::class)->handle(
        User::factory()->create(),
        ownRevenueBudgetData([$field => $value]),
    ))->toThrow(ValidationException::class);

    expect(OwnRevenueBudget::query()->count())->toBe(0)
        ->and(OwnRevenueActivity::query()->count())->toBe(0);
})->with([
    'zero UMA' => ['uma_value', '0'],
    'negative fuel price' => ['fuel_price_per_liter', '-1.25'],
    'too many decimal places' => ['uma_value', '1.23456'],
    'float UMA' => ['uma_value', 113.14],
    'float fuel price' => ['fuel_price_per_liter', 24.5],
    'scientific notation' => ['uma_value', '1e2'],
]);

test('annual value status must agree with whether its value exists', function (array $overrides) {
    expect(fn () => app(InitializeOwnRevenueBudget::class)->handle(
        User::factory()->create(),
        ownRevenueBudgetData($overrides),
    ))->toThrow(ValidationException::class);
})->with([
    'final UMA without value' => [['uma_status' => AnnualValueStatus::Final->value]],
    'provisional fuel without value' => [['fuel_price_status' => AnnualValueStatus::Provisional->value]],
    'pending UMA with value' => [[
        'uma_value' => '113.14',
        'uma_status' => AnnualValueStatus::PendingReview->value,
    ]],
    'pending fuel with value' => [[
        'fuel_price_per_liter' => '24.50',
        'fuel_price_status' => AnnualValueStatus::PendingReview->value,
    ]],
]);

test('initialization rejects a duplicate fiscal year without creating another parent or children', function () {
    $action = app(InitializeOwnRevenueBudget::class);
    $action->handle(User::factory()->create(), ownRevenueBudgetData());

    expect(fn () => $action->handle(User::factory()->create(), ownRevenueBudgetData()))
        ->toThrow(ValidationException::class)
        ->and(OwnRevenueBudget::query()->count())->toBe(1)
        ->and(OwnRevenueActivity::query()->count())->toBe(4);
});

test('draft settings update uses an allowlist and protects immutable and fixed fields', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();
    $budget = app(InitializeOwnRevenueBudget::class)->handle($creator, ownRevenueBudgetData());

    $updated = app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, [
        'institution_name' => 'Institución actualizada',
        'responsible_unit_name' => 'Unidad actualizada',
        'uma_value' => '120.5',
        'uma_status' => AnnualValueStatus::Final->value,
        'fuel_price_per_liter' => '25',
        'fuel_price_status' => AnnualValueStatus::Provisional->value,
        'estimated_income_cents' => 2_000_000,
        'cut_percentage' => '100.00',
        'fiscal_year' => 2040,
        'created_by' => $otherUser->getKey(),
        'status' => OwnRevenueBudgetStatus::Closed->value,
        'region_code' => 'changed',
        'region_name' => 'changed',
        'fuel_budget_month' => 10,
        'cog_status' => CogCatalogStatus::Confirmed->value,
    ]);

    expect($updated->institution_name)->toBe('Institución actualizada')
        ->and($updated->responsible_unit_name)->toBe('Unidad actualizada')
        ->and($updated->uma_value)->toBe('120.5000')
        ->and($updated->uma_status)->toBe(AnnualValueStatus::Final)
        ->and($updated->fuel_price_per_liter)->toBe('25.0000')
        ->and($updated->fuel_price_status)->toBe(AnnualValueStatus::Provisional)
        ->and($updated->fiscal_year)->toBe(2027)
        ->and($updated->created_by)->toBe($creator->getKey())
        ->and($updated->status)->toBe(OwnRevenueBudgetStatus::Draft)
        ->and($updated->region_code)->toBe('02-001')
        ->and($updated->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($updated->fuel_budget_month)->toBe(4)
        ->and($updated->cog_status)->toBe(CogCatalogStatus::PendingConfirmation);
});

test('updating a value to null resets its annual status to pending review', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2028,
        'status' => OwnRevenueBudgetStatus::Draft,
        'uma_value' => '113.1400',
        'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '24.5000',
        'fuel_price_status' => AnnualValueStatus::Final,
    ]);

    $updated = app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, [
        'uma_value' => null,
        'fuel_price_per_liter' => null,
    ]);

    expect($updated->uma_value)->toBeNull()
        ->and($updated->uma_status)->toBe(AnnualValueStatus::PendingReview)
        ->and($updated->fuel_price_per_liter)->toBeNull()
        ->and($updated->fuel_price_status)->toBe(AnnualValueStatus::PendingReview);
});

test('updating a missing annual value without a status starts it as provisional', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2029,
        'status' => OwnRevenueBudgetStatus::Draft,
        'uma_value' => null,
        'uma_status' => AnnualValueStatus::PendingReview,
    ]);

    $updated = app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, ['uma_value' => '123.4']);

    expect($updated->uma_value)->toBe('123.4000')
        ->and($updated->uma_status)->toBe(AnnualValueStatus::Provisional);
});

test('non draft settings cannot be updated and remain unchanged', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2030,
        'status' => OwnRevenueBudgetStatus::ProposalCalculated,
        'institution_name' => 'Original',
    ]);

    expect(fn () => app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, [
        'institution_name' => 'Changed',
        'region_code' => 'Changed',
    ]))->toThrow(ValidationException::class, 'Sólo se puede modificar la configuración de un presupuesto en borrador.');

    expect($budget->refresh()->institution_name)->toBe('Original')
        ->and($budget->region_code)->toBe('02-001');
});

test('budget settings validate percentages and estimated income', function (array $overrides) {
    expect(fn () => app(InitializeOwnRevenueBudget::class)->handle(
        User::factory()->create(),
        ownRevenueBudgetData($overrides),
    ))->toThrow(ValidationException::class);
})->with([
    'negative cut' => [['cut_percentage' => '-0.01']],
    'cut over one hundred' => [['cut_percentage' => '100.01']],
    'cut with excess precision' => [['cut_percentage' => '1.234']],
    'negative estimated income' => [['estimated_income_cents' => -1]],
    'non integer estimated income' => [['estimated_income_cents' => '100']],
]);

test('draft settings reject invalid values without changing the budget', function (array $overrides) {
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2031,
        'status' => OwnRevenueBudgetStatus::Draft,
        'institution_name' => 'Original',
        'uma_value' => '113.1400',
        'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '24.5000',
        'fuel_price_status' => AnnualValueStatus::Final,
    ]);
    $originalSettings = $budget->only([
        'institution_name',
        'estimated_income_cents',
        'cut_percentage',
        'uma_value',
        'uma_status',
        'fuel_price_per_liter',
        'fuel_price_status',
    ]);

    expect(fn () => app(UpdateOwnRevenueBudgetSettings::class)->handle($budget, [
        'institution_name' => 'Changed',
        ...$overrides,
    ]))->toThrow(ValidationException::class);

    expect($budget->refresh()->only(array_keys($originalSettings)))->toBe($originalSettings);
})->with([
    'float UMA' => [['uma_value' => 120.5]],
    'zero fuel price' => [['fuel_price_per_liter' => '0']],
    'pending status with an UMA value' => [['uma_status' => AnnualValueStatus::PendingReview->value]],
    'cut over one hundred' => [['cut_percentage' => '100.01']],
    'negative estimated income' => [['estimated_income_cents' => -1]],
]);
