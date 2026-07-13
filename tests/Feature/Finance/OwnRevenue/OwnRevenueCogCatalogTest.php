<?php

use App\Actions\Finance\OwnRevenue\ConfirmOwnRevenueCogCatalog;
use App\Actions\Finance\OwnRevenue\CopyExpenseClassificationsForYear;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function cogClassificationData(int $fiscalYear, string $specificItemCode, array $overrides = []): array
{
    return array_replace([
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
    ], $overrides);
}

function createCogSource(int $fiscalYear): array
{
    return [
        ExpenseClassification::query()->create(cogClassificationData($fiscalYear, '37501')),
        ExpenseClassification::query()->create(cogClassificationData($fiscalYear, '37502', [
            'chapter_code' => '4000',
            'chapter_name' => 'TRANSFERENCIAS, ASIGNACIONES, SUBSIDIOS Y OTRAS AYUDAS',
            'concept_code' => '4400',
            'concept_name' => 'AYUDAS SOCIALES',
            'generic_item_code' => '4410',
            'generic_item_name' => 'AYUDAS SOCIALES A PERSONAS',
            'specific_item_name' => 'BECAS Y OTRAS AYUDAS PARA PROGRAMAS DE CAPACITACIÓN',
            'expense_type_code' => '2',
            'expense_type_name' => 'GASTO DE CAPITAL',
        ])),
    ];
}

test('it copies every expense classification hierarchy field from an explicit prior year', function () {
    $sourceRows = createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2027,
        'cog_source_year' => 2024,
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => User::factory(),
        'cog_confirmed_at' => now()->subDay(),
    ]);

    $result = app(CopyExpenseClassificationsForYear::class)->handle($budget, 2025);

    $columns = [
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
    $copiedRows = ExpenseClassification::query()
        ->where('fiscal_year', 2027)
        ->orderBy('specific_item_code')
        ->get();

    expect($copiedRows)->toHaveCount(2)
        ->and($copiedRows->pluck('id')->intersect(collect($sourceRows)->pluck('id')))->toBeEmpty()
        ->and($copiedRows->map->only($columns)->values()->all())
        ->toBe(collect($sourceRows)->map->only($columns)->values()->all())
        ->and($copiedRows->every(fn (ExpenseClassification $row): bool => $row->created_at !== null && $row->updated_at !== null))->toBeTrue()
        ->and($result->cog_source_year)->toBe(2025)
        ->and($result->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($result->cog_confirmed_by)->toBeNull()
        ->and($result->cog_confirmed_at)->toBeNull();
});

test('it automatically selects the latest populated year strictly before the budget year', function () {
    createCogSource(2023);
    createCogSource(2025);
    createCogSource(2028);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);

    $result = app(CopyExpenseClassificationsForYear::class)->handle($budget);

    expect($result->cog_source_year)->toBe(2025)
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->pluck('specific_item_code')->all())
        ->toBe(['37501', '37502']);
});

test('an identical repeated copy preserves destination rows and confirmation audit', function () {
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);
    $action = app(CopyExpenseClassificationsForYear::class);
    $action->handle($budget, 2025);
    $confirmed = app(ConfirmOwnRevenueCogCatalog::class)->handle($budget, User::factory()->create());
    $originalRows = ExpenseClassification::query()
        ->where('fiscal_year', 2027)
        ->orderBy('specific_item_code')
        ->get(['id', 'created_at', 'updated_at'])
        ->toArray();

    $result = $action->handle($budget, 2025);

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(2)
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->orderBy('specific_item_code')->get(['id', 'created_at', 'updated_at'])->toArray())->toBe($originalRows)
        ->and($result->cog_source_year)->toBe(2025)
        ->and($result->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($result->cog_confirmed_by)->toBe($confirmed->cog_confirmed_by)
        ->and($result->cog_confirmed_at?->equalTo($confirmed->cog_confirmed_at))->toBeTrue();
});

test('a confirmed identical catalog ignores a different source year and preserves first audit', function () {
    createCogSource(2024);
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);
    $copyAction = app(CopyExpenseClassificationsForYear::class);
    $copyAction->handle($budget, 2024);
    $confirmed = app(ConfirmOwnRevenueCogCatalog::class)->handle($budget, User::factory()->create());

    $result = $copyAction->handle($budget, 2025);

    expect($result->cog_source_year)->toBe(2024)
        ->and($result->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($result->cog_confirmed_by)->toBe($confirmed->cog_confirmed_by)
        ->and($result->cog_confirmed_at?->equalTo($confirmed->cog_confirmed_at))->toBeTrue();
});

test('a pending identical catalog may update its source year provenance', function () {
    createCogSource(2024);
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);
    $action = app(CopyExpenseClassificationsForYear::class);
    $action->handle($budget, 2024);

    $result = $action->handle($budget, 2025);

    expect($result->cog_source_year)->toBe(2025)
        ->and($result->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($result->cog_confirmed_by)->toBeNull()
        ->and($result->cog_confirmed_at)->toBeNull();
});

test('catalog unique collision detection requires an insert into the expense classifications table', function () {
    $action = app(CopyExpenseClassificationsForYear::class);
    $method = new ReflectionMethod($action, 'isDestinationCatalogConflict');
    $ownViolation = (new UniqueConstraintViolationException(
        'sqlite',
        'insert into "expense_classifications" ("fiscal_year", "specific_item_code") values (?, ?)',
        [],
        new RuntimeException('COG unique violation.'),
    ))->setColumns(['fiscal_year', 'specific_item_code']);
    $unrelatedViolation = (new UniqueConstraintViolationException(
        'sqlite',
        'insert into "unrelated_table" ("fiscal_year", "specific_item_code") values (?, ?)',
        [],
        new RuntimeException('Unrelated unique violation.'),
    ))->setColumns(['fiscal_year', 'specific_item_code']);

    expect($method->invoke($action, $ownViolation))->toBeTrue()
        ->and($method->invoke($action, $unrelatedViolation))->toBeFalse();
});

test('catalog unique collision detection recognizes portable table quoting', function (string $sql) {
    $action = app(CopyExpenseClassificationsForYear::class);
    $method = new ReflectionMethod($action, 'isDestinationCatalogConflict');
    $violation = (new UniqueConstraintViolationException(
        'testing',
        $sql,
        [],
        new RuntimeException('COG unique violation.'),
    ))->setIndex('expense_classifications_fiscal_year_specific_item_code_unique');

    expect($method->invoke($action, $violation))->toBeTrue();
})->with([
    'SQLite and PostgreSQL quotes' => 'insert into "expense_classifications" ("fiscal_year") values (?)',
    'MySQL quotes' => 'INSERT INTO `expense_classifications` (`fiscal_year`) VALUES (?)',
    'SQL Server quotes' => 'insert into [expense_classifications] ([fiscal_year]) values (?)',
    'schema qualified PostgreSQL' => 'insert into "finance"."expense_classifications" ("fiscal_year") values (?)',
]);

test('an unrelated unique violation with matching metadata is rethrown unchanged', function (string $metadata) {
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);
    $grammar = DB::connection()->getQueryGrammar();
    $violation = new UniqueConstraintViolationException(
        DB::getDefaultConnection(),
        'insert into '.$grammar->wrapTable('unrelated_catalogs').' ("fiscal_year", "specific_item_code") values (?, ?)',
        [],
        new RuntimeException('Unrelated unique constraint.'),
    );
    $metadata === 'columns'
        ? $violation->setColumns(['fiscal_year', 'specific_item_code'])
        : $violation->setIndex('expense_classifications_fiscal_year_specific_item_code_unique');

    OwnRevenueBudget::retrieved(function () use ($violation): never {
        throw $violation;
    });

    try {
        app(CopyExpenseClassificationsForYear::class)->handle($budget, 2025);
    } catch (Throwable $caughtException) {
    } finally {
        OwnRevenueBudget::flushEventListeners();
    }

    expect($caughtException ?? null)->toBe($violation);
})->with(['columns', 'index']);

test('a destination catalog unique violation is translated to a readable conflict', function () {
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);
    $grammar = DB::connection()->getQueryGrammar();
    $violation = (new UniqueConstraintViolationException(
        DB::getDefaultConnection(),
        'insert into '.$grammar->wrapTable((new ExpenseClassification)->getTable()).' ("fiscal_year", "specific_item_code") values (?, ?)',
        [],
        new RuntimeException('Destination unique constraint.'),
    ))->setColumns(['fiscal_year', 'specific_item_code']);

    OwnRevenueBudget::retrieved(function () use ($violation): never {
        throw $violation;
    });

    try {
        app(CopyExpenseClassificationsForYear::class)->handle($budget, 2025);
    } catch (Throwable $caughtException) {
    } finally {
        OwnRevenueBudget::flushEventListeners();
    }

    expect($caughtException ?? null)->toBeInstanceOf(ValidationException::class)
        ->and($caughtException->errors())->toBe([
            'catalog' => ['El catálogo COG del ejercicio destino cambió durante la copia; inténtelo nuevamente.'],
        ]);
});

test('a different incomplete or excessive destination catalog is rejected without partial changes', function (string $destinationCase) {
    createCogSource(2025);
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2027,
        'cog_source_year' => 2024,
        'cog_status' => CogCatalogStatus::PendingConfirmation,
    ]);

    if ($destinationCase === 'different') {
        ExpenseClassification::query()->create(cogClassificationData(2027, '37501', ['specific_item_name' => 'DISTINTO']));
        ExpenseClassification::query()->create(cogClassificationData(2027, '37502'));
    } elseif ($destinationCase === 'incomplete') {
        ExpenseClassification::query()->create(cogClassificationData(2027, '37501'));
    } else {
        ExpenseClassification::query()->create(cogClassificationData(2027, '37501'));
        ExpenseClassification::query()->create(cogClassificationData(2027, '37502', [
            'chapter_code' => '4000',
            'chapter_name' => 'TRANSFERENCIAS, ASIGNACIONES, SUBSIDIOS Y OTRAS AYUDAS',
            'concept_code' => '4400',
            'concept_name' => 'AYUDAS SOCIALES',
            'generic_item_code' => '4410',
            'generic_item_name' => 'AYUDAS SOCIALES A PERSONAS',
            'specific_item_name' => 'BECAS Y OTRAS AYUDAS PARA PROGRAMAS DE CAPACITACIÓN',
            'expense_type_code' => '2',
            'expense_type_name' => 'GASTO DE CAPITAL',
        ]));
        ExpenseClassification::query()->create(cogClassificationData(2027, '99999'));
    }
    $before = ExpenseClassification::query()->where('fiscal_year', 2027)->orderBy('specific_item_code')->get()->toArray();

    expect(fn () => app(CopyExpenseClassificationsForYear::class)->handle($budget, 2025))
        ->toThrow(ValidationException::class, 'El catálogo COG del ejercicio destino entra en conflicto con el catálogo de origen.');

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->orderBy('specific_item_code')->get()->toArray())->toBe($before)
        ->and($budget->refresh()->cog_source_year)->toBe(2024)
        ->and($budget->cog_status)->toBe(CogCatalogStatus::PendingConfirmation);
})->with(['different', 'incomplete', 'excessive']);

test('missing automatic source produces a readable error without partial rows', function () {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);

    expect(fn () => app(CopyExpenseClassificationsForYear::class)->handle($budget))
        ->toThrow(ValidationException::class, 'No existe un catálogo COG anterior para copiar.');

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(0)
        ->and($budget->refresh()->cog_source_year)->toBeNull()
        ->and($budget->cog_status)->toBe(CogCatalogStatus::PendingConfirmation);
});

test('an explicit source must exist contain rows and be earlier than the destination', function (int $sourceYear, string $message) {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => null]);
    createCogSource(2028);

    expect(fn () => app(CopyExpenseClassificationsForYear::class)->handle($budget, $sourceYear))
        ->toThrow(ValidationException::class, $message);

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(0)
        ->and($budget->refresh()->cog_source_year)->toBeNull()
        ->and($budget->cog_status)->toBe(CogCatalogStatus::PendingConfirmation);
})->with([
    'missing' => [2025, 'El catálogo COG de origen no existe o no contiene partidas.'],
    'same year' => [2027, 'El catálogo COG de origen debe pertenecer a un ejercicio anterior.'],
    'future year' => [2028, 'El catálogo COG de origen debe pertenecer a un ejercicio anterior.'],
]);

test('confirmation requires destination catalog rows and leaves audit fields empty on failure', function () {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => 2025]);

    expect(fn () => app(ConfirmOwnRevenueCogCatalog::class)->handle($budget, User::factory()->create()))
        ->toThrow(ValidationException::class, 'No se puede confirmar un catálogo COG sin partidas.');

    expect($budget->refresh()->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($budget->cog_confirmed_by)->toBeNull()
        ->and($budget->cog_confirmed_at)->toBeNull();
});

test('confirmation rejects a user that is not persisted without changing audit state', function (string $userState) {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => 2025]);
    ExpenseClassification::query()->create(cogClassificationData(2027, '37501'));
    $user = User::factory()->create();

    if ($userState === 'unsaved') {
        $user = User::factory()->make();
    } else {
        User::query()->whereKey($user->getKey())->delete();
    }

    expect(fn () => app(ConfirmOwnRevenueCogCatalog::class)->handle($budget, $user))
        ->toThrow(ValidationException::class, 'El usuario que confirma el catálogo COG debe existir.');

    expect($budget->refresh()->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($budget->cog_confirmed_by)->toBeNull()
        ->and($budget->cog_confirmed_at)->toBeNull();
})->with(['unsaved', 'deleted after loading']);

test('confirmation records the first audit event and repeated confirmation preserves it', function () {
    Carbon::setTestNow('2026-07-13 10:00:00');
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'cog_source_year' => 2025]);
    ExpenseClassification::query()->create(cogClassificationData(2027, '37501'));
    $action = app(ConfirmOwnRevenueCogCatalog::class);

    $confirmed = $action->handle($budget, $firstUser);
    Carbon::setTestNow('2026-07-14 12:00:00');
    $repeated = $action->handle($budget, $secondUser);

    expect($confirmed->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($confirmed->cog_confirmed_by)->toBe($firstUser->getKey())
        ->and($confirmed->cog_confirmed_at?->toDateTimeString())->toBe('2026-07-13 10:00:00')
        ->and($confirmed->cogConfirmedBy->is($firstUser))->toBeTrue()
        ->and($repeated->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($repeated->cog_confirmed_by)->toBe($firstUser->getKey())
        ->and($repeated->cog_confirmed_at?->toDateTimeString())->toBe('2026-07-13 10:00:00');
});

test('confirmation rejects incoherent audit state without changing it', function () {
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2027,
        'cog_status' => CogCatalogStatus::PendingConfirmation,
        'cog_confirmed_by' => User::factory(),
        'cog_confirmed_at' => now(),
    ]);
    ExpenseClassification::query()->create(cogClassificationData(2027, '37501'));

    expect(fn () => app(ConfirmOwnRevenueCogCatalog::class)->handle($budget, User::factory()->create()))
        ->toThrow(ValidationException::class, 'El estado de confirmación del catálogo COG es incoherente.');

    expect($budget->refresh()->cog_status)->toBe(CogCatalogStatus::PendingConfirmation)
        ->and($budget->cog_confirmed_by)->not->toBeNull()
        ->and($budget->cog_confirmed_at)->not->toBeNull();
});
