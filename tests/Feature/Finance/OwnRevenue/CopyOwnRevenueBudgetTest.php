<?php

use App\Actions\Finance\OwnRevenue\CopyOwnRevenueBudget;
use App\Enums\Finance\OwnRevenue\AnnualValueStatus;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\OwnRevenue\OwnRevenueSignatory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function copyBudgetCogRow(int $fiscalYear, string $specificItemCode): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $fiscalYear,
        'chapter_code' => '3000',
        'chapter_name' => 'SERVICIOS GENERALES',
        'concept_code' => '3700',
        'concept_name' => 'SERVICIOS DE TRASLADO Y VIÁTICOS',
        'generic_item_code' => '3750',
        'generic_item_name' => 'VIÁTICOS EN EL PAÍS',
        'specific_item_code' => $specificItemCode,
        'specific_item_name' => "VIÁTICOS EN EL PAÍS {$specificItemCode}",
        'expense_type_code' => '1',
        'expense_type_name' => 'GASTO CORRIENTE',
    ]);
}

function copyBudgetSource(User $createdBy, int $fiscalYear = 2026): OwnRevenueBudget
{
    $budget = OwnRevenueBudget::factory()->create([
        'created_by' => $createdBy,
        'fiscal_year' => $fiscalYear,
        'status' => OwnRevenueBudgetStatus::Closed,
        'institution_name' => 'Institución histórica',
        'responsible_unit_code' => 'UR-77',
        'responsible_unit_name' => 'Unidad histórica',
        'budget_program_code' => 'BP-88',
        'budget_program_name' => 'Programa histórico',
        'component_code' => 'C-99',
        'component_name' => 'Componente histórico',
        'official_activity_code' => 'OA-11',
        'official_activity_name' => 'Actividad oficial histórica',
        'estimated_income_cents' => 9_876_543,
        'cut_percentage' => '12.50',
        'uma_value' => '120.5000',
        'uma_status' => AnnualValueStatus::Final,
        'fuel_price_per_liter' => '26.2500',
        'fuel_price_status' => AnnualValueStatus::Final,
        'cog_source_year' => 2025,
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => $createdBy,
        'cog_confirmed_at' => now()->subDay(),
    ]);

    $budget->activities()->createMany([
        ['code' => 'A01', 'name' => 'Investigación histórica', 'sort_order' => 40],
        ['code' => 'A02', 'name' => 'Docencia histórica', 'sort_order' => 10],
        ['code' => 'A03', 'name' => 'Difusión histórica', 'sort_order' => 30],
        ['code' => 'A04', 'name' => 'Gestión histórica', 'sort_order' => 20],
    ]);

    $budget->signatories()->createMany([
        [
            'role_key' => 'prepared_by',
            'name' => 'Ana Preparadora',
            'position' => 'Responsable financiera',
            'academic_degree' => 'C.P.',
            'sort_order' => 2,
        ],
        [
            'role_key' => 'reviewed_by',
            'name' => 'Beatriz Revisora',
            'position' => 'Subdirectora administrativa',
            'academic_degree' => null,
            'sort_order' => 1,
        ],
        [
            'role_key' => 'authorized_by',
            'name' => 'Carlos Autorizador',
            'position' => 'Director',
            'academic_degree' => 'Mtro.',
            'sort_order' => 3,
        ],
    ]);

    return $budget;
}

test('it copies annual institutional configuration activities signatories and COG while resetting reviewed values', function () {
    $sourceCreator = User::factory()->create();
    $destinationCreator = User::factory()->create();
    $source = copyBudgetSource($sourceCreator);
    $sourceCog = [
        copyBudgetCogRow(2026, '37501'),
        copyBudgetCogRow(2026, '37502'),
    ];
    $sourceSnapshot = [
        'budget' => $source->fresh()->toArray(),
        'activities' => $source->activities()->orderBy('code')->get()->toArray(),
        'signatories' => $source->signatories()->orderBy('role_key')->get()->toArray(),
        'cog' => ExpenseClassification::query()->where('fiscal_year', 2026)->orderBy('specific_item_code')->get()->toArray(),
    ];

    $destination = app(CopyOwnRevenueBudget::class)->handle($source, 2027, $destinationCreator);

    $institutionalFields = [
        'institution_name',
        'responsible_unit_code',
        'responsible_unit_name',
        'budget_program_code',
        'budget_program_name',
        'component_code',
        'component_name',
        'official_activity_code',
        'official_activity_name',
    ];
    $activityFields = ['code', 'name', 'sort_order'];
    $signatoryFields = ['role_key', 'name', 'position', 'academic_degree', 'sort_order'];
    $cogFields = [
        'chapter_code',
        'chapter_name',
        'concept_code',
        'concept_name',
        'generic_item_code',
        'generic_item_name',
        'specific_item_code',
        'specific_item_name',
        'expense_type_code',
        'expense_type_name',
    ];
    $destinationActivities = $destination->activities()->orderBy('code')->get();
    $destinationSignatories = $destination->signatories()->orderBy('role_key')->get();
    $destinationCog = ExpenseClassification::query()->where('fiscal_year', 2027)->orderBy('specific_item_code')->get();

    expect($destination->only($institutionalFields))->toBe($source->only($institutionalFields))
        ->and($destination->fiscal_year)->toBe(2027)
        ->and($destination->created_by)->toBe($destinationCreator->getKey())
        ->and($destination->status)->toBe(OwnRevenueBudgetStatus::Draft)
        ->and($destination->region_code)->toBe('02-001')
        ->and($destination->region_name)->toBe('Felipe Carrillo Puerto')
        ->and($destination->fuel_budget_month)->toBe(4)
        ->and($destination->estimated_income_cents)->toBeNull()
        ->and($destination->cut_percentage)->toBeNull()
        ->and($destination->uma_value)->toBeNull()
        ->and($destination->uma_status)->toBe(AnnualValueStatus::PendingReview)
        ->and($destination->fuel_price_per_liter)->toBeNull()
        ->and($destination->fuel_price_status)->toBe(AnnualValueStatus::PendingReview)
        ->and($destination->cog_source_year)->toBe(2026)
        ->and($destination->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($destination->cog_confirmed_by)->toBeNull()
        ->and($destination->cog_confirmed_at)->toBeNull()
        ->and($destinationActivities->map->only($activityFields)->values()->all())
        ->toBe($source->activities()->orderBy('code')->get()->map->only($activityFields)->values()->all())
        ->and($destinationActivities)->toHaveCount(4)
        ->and($destinationActivities->pluck('code')->unique())->toHaveCount(4)
        ->and($destinationSignatories->map->only($signatoryFields)->values()->all())
        ->toBe($source->signatories()->orderBy('role_key')->get()->map->only($signatoryFields)->values()->all())
        ->and($destinationSignatories)->toHaveCount(3)
        ->and($destinationCog->map->only($cogFields)->values()->all())
        ->toBe(collect($sourceCog)->map->only($cogFields)->values()->all());

    expect([
        'budget' => $source->fresh()->toArray(),
        'activities' => $source->activities()->orderBy('code')->get()->toArray(),
        'signatories' => $source->signatories()->orderBy('role_key')->get()->toArray(),
        'cog' => ExpenseClassification::query()->where('fiscal_year', 2026)->orderBy('specific_item_code')->get()->toArray(),
    ])->toBe($sourceSnapshot);
});

test('it rejects same and past destination years without creating records', function (int $destinationYear) {
    $creator = User::factory()->create();
    $source = copyBudgetSource($creator, 2026);
    copyBudgetCogRow(2026, '37501');
    $budgetCount = OwnRevenueBudget::query()->count();

    expect(fn () => app(CopyOwnRevenueBudget::class)->handle($source, $destinationYear, $creator))
        ->toThrow(ValidationException::class, 'El ejercicio fiscal destino debe ser posterior al ejercicio de origen.');

    expect(OwnRevenueBudget::query()->count())->toBe($budgetCount);
})->with(['same year' => 2026, 'past year' => 2025]);

test('it rejects destination years that do not contain four digits', function (int $destinationYear) {
    $creator = User::factory()->create();
    $source = copyBudgetSource($creator);

    try {
        app(CopyOwnRevenueBudget::class)->handle($source, $destinationYear, $creator);
    } catch (ValidationException $exception) {
    }

    expect($exception->errors())->toHaveKey('fiscal_year');
})->with(['too short' => 999, 'too long' => 10000]);

test('an existing destination is rejected without changing it or its children', function () {
    $creator = User::factory()->create();
    $source = copyBudgetSource($creator);
    copyBudgetCogRow(2026, '37501');
    $existing = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2027,
        'institution_name' => 'Destino intacto',
    ]);
    $existing->activities()->create(['code' => 'CUSTOM', 'name' => 'Conservar', 'sort_order' => 9]);
    $before = [
        'budget' => $existing->fresh()->toArray(),
        'activities' => $existing->activities()->get()->toArray(),
    ];

    expect(fn () => app(CopyOwnRevenueBudget::class)->handle($source, 2027, $creator))
        ->toThrow(ValidationException::class, 'Ya existe un presupuesto de ingresos propios para el ejercicio fiscal destino.');

    expect([
        'budget' => $existing->fresh()->toArray(),
        'activities' => $existing->activities()->get()->toArray(),
    ])->toBe($before);
});

test('missing source COG rolls back the destination and all newly created children', function () {
    $creator = User::factory()->create();
    $source = copyBudgetSource($creator);
    $countsBefore = [
        OwnRevenueBudget::query()->count(),
        OwnRevenueActivity::query()->count(),
        OwnRevenueSignatory::query()->count(),
        ExpenseClassification::query()->count(),
    ];

    expect(fn () => app(CopyOwnRevenueBudget::class)->handle($source, 2027, $creator))
        ->toThrow(ValidationException::class, 'El catálogo COG de origen no existe o no contiene partidas.');

    expect([
        OwnRevenueBudget::query()->count(),
        OwnRevenueActivity::query()->count(),
        OwnRevenueSignatory::query()->count(),
        ExpenseClassification::query()->count(),
    ])->toBe($countsBefore)
        ->and(OwnRevenueBudget::query()->where('fiscal_year', 2027)->exists())->toBeFalse();
});

test('a malformed source activity set rolls back the complete copy without mutating the source', function (string $activitySet) {
    $creator = User::factory()->create();
    $source = copyBudgetSource($creator);
    copyBudgetCogRow(2026, '37501');

    if ($activitySet === 'missing') {
        $source->activities()->where('code', 'A04')->delete();
    } else {
        $source->activities()->create([
            'code' => 'A05',
            'name' => 'Actividad no canónica',
            'sort_order' => 50,
        ]);
    }

    $sourceSnapshot = [
        'budget' => $source->fresh()->toArray(),
        'activities' => $source->activities()->orderBy('code')->get()->toArray(),
        'signatories' => $source->signatories()->orderBy('role_key')->get()->toArray(),
        'cog' => ExpenseClassification::query()->where('fiscal_year', 2026)->orderBy('specific_item_code')->get()->toArray(),
    ];
    $countsBefore = [
        OwnRevenueBudget::query()->count(),
        OwnRevenueActivity::query()->count(),
        OwnRevenueSignatory::query()->count(),
        ExpenseClassification::query()->count(),
    ];

    $exception = null;

    try {
        app(CopyOwnRevenueBudget::class)->handle($source, 2027, $creator);
    } catch (ValidationException $caughtException) {
        $exception = $caughtException;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class);

    expect($exception->errors())->toHaveKey('source_budget.activities')
        ->and([
            OwnRevenueBudget::query()->count(),
            OwnRevenueActivity::query()->count(),
            OwnRevenueSignatory::query()->count(),
            ExpenseClassification::query()->count(),
        ])->toBe($countsBefore)
        ->and(OwnRevenueBudget::query()->where('fiscal_year', 2027)->exists())->toBeFalse()
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->exists())->toBeFalse()
        ->and([
            'budget' => $source->fresh()->toArray(),
            'activities' => $source->activities()->orderBy('code')->get()->toArray(),
            'signatories' => $source->signatories()->orderBy('role_key')->get()->toArray(),
            'cog' => ExpenseClassification::query()->where('fiscal_year', 2026)->orderBy('specific_item_code')->get()->toArray(),
        ])->toBe($sourceSnapshot);
})->with(['missing canonical code' => 'missing', 'extra code' => 'extra']);

test('an unsaved or deleted source is rejected with a readable source error', function (string $sourceState) {
    $creator = User::factory()->create();
    $source = $sourceState === 'unsaved'
        ? OwnRevenueBudget::factory()->make(['fiscal_year' => 2026])
        : copyBudgetSource($creator);

    if ($sourceState === 'deleted') {
        $source->delete();
    }

    try {
        app(CopyOwnRevenueBudget::class)->handle($source, 2027, $creator);
    } catch (ValidationException $exception) {
    }

    expect($exception->errors())->toHaveKey('source')
        ->and(OwnRevenueBudget::query()->where('fiscal_year', 2027)->exists())->toBeFalse();
})->with(['unsaved', 'deleted']);

test('an unsaved or deleted creator is rejected with a readable creator error', function (string $creatorState) {
    $sourceCreator = User::factory()->create();
    $source = copyBudgetSource($sourceCreator);
    $creator = $creatorState === 'unsaved'
        ? User::factory()->make()
        : User::factory()->create();

    if ($creatorState === 'deleted') {
        $creator->delete();
    }

    try {
        app(CopyOwnRevenueBudget::class)->handle($source, 2027, $creator);
    } catch (ValidationException $exception) {
    }

    expect($exception->errors())->toHaveKey('creator')
        ->and(OwnRevenueBudget::query()->where('fiscal_year', 2027)->exists())->toBeFalse();
})->with(['unsaved', 'deleted']);
