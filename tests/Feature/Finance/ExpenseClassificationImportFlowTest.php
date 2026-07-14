<?php

use App\Actions\Finance\ImportExpenseClassifications;
use App\Enums\Finance\OwnRevenue\CogCatalogStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\CogCatalogSpreadsheetParser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->withoutVite();
});

function expenseClassificationImportUser(UserRole $role = UserRole::FinanceManager): User
{
    $user = User::factory()->create([
        'email' => fake()->unique()->userName().'@crenfcp.edu.mx',
    ]);

    AuthorizedAccess::create([
        'email' => $user->email,
        'role' => $role,
        'is_active' => true,
    ]);

    return $user;
}

function minimalCogUploadFile(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cog').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="COG" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
      <row r="1"><c r="A1" t="inlineStr"><is><t>Cve Capítulo</t></is></c><c r="B1" t="inlineStr"><is><t>Capítulo</t></is></c><c r="C1" t="inlineStr"><is><t>Cve Concepto</t></is></c><c r="D1" t="inlineStr"><is><t>Concepto</t></is></c><c r="E1" t="inlineStr"><is><t>Cve Partida Genérica</t></is></c><c r="F1" t="inlineStr"><is><t>Partida Genérica</t></is></c><c r="G1" t="inlineStr"><is><t>Cve Partida Específica</t></is></c><c r="H1" t="inlineStr"><is><t>Partida Específica</t></is></c><c r="I1" t="inlineStr"><is><t>Cve Tipo de Gasto</t></is></c><c r="J1" t="inlineStr"><is><t>Tipo de Gasto</t></is></c></row>
      <row r="2"><c r="A2" t="inlineStr"><is><t>3000</t></is></c><c r="B2" t="inlineStr"><is><t>Servicios Generales</t></is></c><c r="C2" t="inlineStr"><is><t>3700</t></is></c><c r="D2" t="inlineStr"><is><t>Servicios de traslado y viáticos</t></is></c><c r="E2" t="inlineStr"><is><t>3750</t></is></c><c r="F2" t="inlineStr"><is><t>Viáticos en el país</t></is></c><c r="G2" t="inlineStr"><is><t>37501</t></is></c><c r="H2" t="inlineStr"><is><t>Viáticos en el país</t></is></c><c r="I2" t="inlineStr"><is><t>1</t></is></c><c r="J2" t="inlineStr"><is><t>Gasto corriente</t></is></c></row>
    </sheetData></worksheet>
    XML);
    $zip->close();

    return new UploadedFile($path, 'Clasificacion_Objeto_de_Gasto_2026_generado.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function parsedExpenseClassificationRow(array $overrides = []): array
{
    return array_replace([
        'chapter_code' => '3000',
        'chapter_name' => 'Servicios Generales',
        'concept_code' => '3700',
        'concept_name' => 'Servicios de traslado y viáticos',
        'generic_item_code' => '3750',
        'generic_item_name' => 'Viáticos en el país',
        'specific_item_code' => '37501',
        'specific_item_name' => 'Viáticos actualizados',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ], $overrides);
}

test('administrative finance roles can import expense classifications from an XLSX file', function (UserRole $role) {
    Storage::fake('local');
    $user = expenseClassificationImportUser($role);

    $this->actingAs($user)
        ->get(route('finance.expense-classifications.imports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('finance/expense-classifications/imports/create'));

    $this->actingAs($user)
        ->post(route('finance.expense-classifications.imports.store'), [
            'fiscal_year' => 2026,
            'catalog_file' => minimalCogUploadFile(),
        ])
        ->assertRedirect(route('finance.expense-classifications.imports.create'));

    expect(ExpenseClassification::query()->count())->toBe(1);

    $this->assertDatabaseHas('expense_classifications', [
        'fiscal_year' => 2026,
        'specific_item_code' => '37501',
        'specific_item_name' => 'Viáticos en el país',
    ]);
})->with([
    'owner' => UserRole::Owner,
    'admin' => UserRole::Admin,
    'finance manager' => UserRole::FinanceManager,
]);

test('read only finance roles cannot open or submit the expense classification import', function (UserRole $role) {
    Storage::fake('local');
    $user = expenseClassificationImportUser($role);

    $this->actingAs($user)
        ->get(route('finance.expense-classifications.imports.create'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('finance.expense-classifications.imports.store'), [
            'fiscal_year' => 2026,
            'catalog_file' => minimalCogUploadFile(),
        ])
        ->assertForbidden();

    expect(ExpenseClassification::query()->count())->toBe(0);
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);

test('the import action rejects read only finance roles before parsing or mutating COG', function (UserRole $role) {
    $user = expenseClassificationImportUser($role);
    mock(CogCatalogSpreadsheetParser::class)
        ->shouldNotReceive('parse');

    expect(fn () => app(ImportExpenseClassifications::class)->handle($user, 2027, '/tmp/forbidden-cog.xlsx'))
        ->toThrow(AuthorizationException::class);

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->exists())->toBeFalse();
})->with([
    'finance assistant' => UserRole::FinanceAssistant,
    'finance auditor' => UserRole::FinanceAuditor,
]);

test('a confirmed own revenue catalog blocks imports without changing rows or audit', function () {
    $confirmedBy = User::factory()->create();
    $confirmedAt = now()->subDay()->startOfSecond();
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2027,
        'cog_source_year' => 2026,
        'cog_status' => CogCatalogStatus::Confirmed,
        'cog_confirmed_by' => $confirmedBy,
        'cog_confirmed_at' => $confirmedAt,
    ]);
    $classification = ExpenseClassification::query()->create([
        'fiscal_year' => 2027,
        ...parsedExpenseClassificationRow(['specific_item_name' => 'Nombre auditado']),
    ]);
    mock(CogCatalogSpreadsheetParser::class)
        ->shouldReceive('parse')
        ->once()
        ->with('/tmp/confirmed-cog.xlsx')
        ->andReturn([
            parsedExpenseClassificationRow(),
            parsedExpenseClassificationRow([
                'specific_item_code' => '37502',
                'specific_item_name' => 'Partida nueva',
            ]),
        ]);

    expect(fn () => app(ImportExpenseClassifications::class)->handle(expenseClassificationImportUser(), 2027, '/tmp/confirmed-cog.xlsx'))
        ->toThrow(ValidationException::class, 'No se puede importar sobre un catálogo COG confirmado.');

    expect(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(1)
        ->and($classification->refresh()->specific_item_name)->toBe('Nombre auditado')
        ->and($budget->refresh()->cog_status)->toBe(CogCatalogStatus::Confirmed)
        ->and($budget->cog_confirmed_by)->toBe($confirmedBy->getKey())
        ->and($budget->cog_confirmed_at?->equalTo($confirmedAt))->toBeTrue();
});

test('imports remain allowed for a pending own revenue catalog or a year without a budget', function (bool $createPendingBudget) {
    if ($createPendingBudget) {
        OwnRevenueBudget::factory()->create([
            'fiscal_year' => 2027,
            'cog_source_year' => 2026,
            'cog_status' => CogCatalogStatus::PendingConfirmation,
        ]);
    }
    mock(CogCatalogSpreadsheetParser::class)
        ->shouldReceive('parse')
        ->once()
        ->andReturn([parsedExpenseClassificationRow()]);

    $imported = app(ImportExpenseClassifications::class)->handle(expenseClassificationImportUser(), 2027, '/tmp/allowed-cog.xlsx');

    expect($imported)->toBe(1)
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->count())->toBe(1)
        ->and(ExpenseClassification::query()->where('fiscal_year', 2027)->value('specific_item_name'))
        ->toBe('Viáticos actualizados');
})->with([
    'pending budget' => true,
    'without budget' => false,
]);
