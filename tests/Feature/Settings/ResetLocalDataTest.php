<?php

use App\Actions\Settings\ResetLocalData;
use App\Enums\Settings\LocalDataResetScope;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\ChargeConcept;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\Finance\U300\U300Program;
use App\Models\FinanceFolioSequence;
use App\Models\OfficialFeeSchedule;
use App\Models\PaymentProcedure;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\ReceiptCancellation;
use App\Models\SeqDeposit;
use App\Models\SeqReportExport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app()->detectEnvironment(fn (): string => 'local');
});

afterEach(function () {
    app()->detectEnvironment(fn (): string => 'testing');
});

test('ventanilla reset deletes operations and only its folio sequences', function () {
    ReceiptCancellation::factory()->create();
    SeqDeposit::factory()->create();
    SeqReportExport::factory()->create();
    $chargeConcept = ChargeConcept::factory()->create();
    $officialFeeSchedule = OfficialFeeSchedule::factory()->create();
    $u300Program = createU300Program();
    $ownRevenueBudget = OwnRevenueBudget::factory()->create();

    foreach (['procedure', 'receipt_internal', 'receipt_external', 'future_module'] as $sequenceKey) {
        FinanceFolioSequence::query()->create([
            'sequence_key' => $sequenceKey,
            'year' => 2026,
            'next_number' => 2,
        ]);
    }

    $result = app(ResetLocalData::class)->handle(LocalDataResetScope::Ventanilla);

    expect($result->scope)->toBe(LocalDataResetScope::Ventanilla)
        ->and($result->deletedRecords)->toBeGreaterThan(0)
        ->and(PaymentProcedure::query()->count())->toBe(0)
        ->and(PaymentTransaction::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(ReceiptCancellation::query()->count())->toBe(0)
        ->and(SeqDeposit::query()->count())->toBe(0)
        ->and(SeqReportExport::query()->count())->toBe(0)
        ->and(FinanceFolioSequence::query()->pluck('sequence_key')->all())->toBe(['future_module'])
        ->and($chargeConcept->fresh())->not->toBeNull()
        ->and($officialFeeSchedule->fresh())->not->toBeNull()
        ->and($u300Program->fresh())->not->toBeNull()
        ->and($ownRevenueBudget->fresh())->not->toBeNull();
});

test('u300 reset deletes its records and files without touching other modules', function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('u300/imports/source.pdf', 'u300');
    Storage::disk('public')->put('u300/technical-sheets/reference-photos/photo.jpg', 'photo');
    Storage::disk('local')->put('own-revenue/imports/keep.xlsx', 'budget');

    createU300Program();
    $ownRevenueBudget = OwnRevenueBudget::factory()->create();
    $paymentProcedure = PaymentProcedure::factory()->create();

    $result = app(ResetLocalData::class)->handle(LocalDataResetScope::U300);

    expect($result->scope)->toBe(LocalDataResetScope::U300)
        ->and(U300Program::query()->count())->toBe(0)
        ->and($ownRevenueBudget->fresh())->not->toBeNull()
        ->and($paymentProcedure->fresh())->not->toBeNull();

    Storage::disk('local')->assertMissing('u300/imports/source.pdf');
    Storage::disk('public')->assertMissing('u300/technical-sheets/reference-photos/photo.jpg');
    Storage::disk('local')->assertExists('own-revenue/imports/keep.xlsx');
});

test('own revenue reset preserves shared classifications and their source file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('own-revenue/imports/source.xlsx', 'import');
    Storage::disk('local')->put('own-revenue/exports/export.xlsx', 'export');
    Storage::disk('local')->put('finance/own-revenue/1/expense-dossiers/evidence.pdf', 'evidence');
    Storage::disk('local')->put('finance/expense-classifications/imports/source.xlsx', 'catalog');

    OwnRevenueBudget::factory()->create();
    $classification = createExpenseClassification();
    $u300Program = createU300Program();
    $paymentProcedure = PaymentProcedure::factory()->create();

    $result = app(ResetLocalData::class)->handle(LocalDataResetScope::OwnRevenue);

    expect($result->scope)->toBe(LocalDataResetScope::OwnRevenue)
        ->and(OwnRevenueBudget::query()->count())->toBe(0)
        ->and($classification->fresh())->not->toBeNull()
        ->and($u300Program->fresh())->not->toBeNull()
        ->and($paymentProcedure->fresh())->not->toBeNull();

    Storage::disk('local')->assertMissing('own-revenue/imports/source.xlsx');
    Storage::disk('local')->assertMissing('own-revenue/exports/export.xlsx');
    Storage::disk('local')->assertMissing('finance/own-revenue/1/expense-dossiers/evidence.pdf');
    Storage::disk('local')->assertExists('finance/expense-classifications/imports/source.xlsx');
});

test('database failures roll back the module and keep its files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('own-revenue/imports/keep.xlsx', 'import');
    OwnRevenueBudget::factory()->create();

    DB::statement("CREATE TRIGGER block_budget_delete BEFORE DELETE ON own_revenue_budgets BEGIN SELECT RAISE(ABORT, 'blocked'); END");

    expect(fn () => app(ResetLocalData::class)->handle(LocalDataResetScope::OwnRevenue))
        ->toThrow(QueryException::class);

    expect(OwnRevenueBudget::query()->count())->toBe(1);
    Storage::disk('local')->assertExists('own-revenue/imports/keep.xlsx');
});

test('file failures after commit are reported without restoring database records', function () {
    OwnRevenueBudget::factory()->create();
    Storage::shouldReceive('disk')
        ->with('local')
        ->andThrow(new RuntimeException('disk unavailable'));

    $result = app(ResetLocalData::class)->handle(LocalDataResetScope::OwnRevenue);

    expect(OwnRevenueBudget::query()->count())->toBe(0)
        ->and($result->fileWarnings)->toHaveCount(3);
});

test('reset action refuses to run outside local', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(ResetLocalData::class)->handle(LocalDataResetScope::U300))
        ->toThrow(LogicException::class, 'El reinicio sólo está disponible en local.');
});

test('general reset removes all functional data and recreates only the institutional owner', function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('u300/imports/source.pdf', 'u300');
    Storage::disk('public')->put('u300/technical-sheets/reference-photos/photo.jpg', 'photo');
    Storage::disk('local')->put('own-revenue/imports/source.xlsx', 'budget');
    Storage::disk('local')->put('finance/expense-classifications/imports/source.xlsx', 'catalog');

    $secondaryUser = User::factory()->create(['email' => 'admin.local@crenfcp.edu.mx']);
    AuthorizedAccess::query()->create([
        'email' => $secondaryUser->email,
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
    ReceiptCancellation::factory()->create();
    createU300Program();
    OwnRevenueBudget::factory()->create();
    createExpenseClassification();
    ChargeConcept::factory()->create();
    FinanceFolioSequence::query()->create([
        'sequence_key' => 'future_module',
        'year' => 2026,
        'next_number' => 3,
    ]);

    $result = app(ResetLocalData::class)->handle(LocalDataResetScope::All);

    expect($result->scope)->toBe(LocalDataResetScope::All)
        ->and($result->deletedRecords)->toBeGreaterThan(0)
        ->and(User::query()->pluck('email')->all())->toBe(['administrador.siga@crenfcp.edu.mx'])
        ->and(AuthorizedAccess::query()->pluck('email')->all())->toBe(['administrador.siga@crenfcp.edu.mx'])
        ->and(AuthorizedAccess::query()->firstOrFail()->role)->toBe(UserRole::Owner)
        ->and(DB::table('migrations')->count())->toBeGreaterThan(0)
        ->and(ExpenseClassification::query()->count())->toBe(0)
        ->and(ChargeConcept::query()->count())->toBe(0)
        ->and(U300Program::query()->count())->toBe(0)
        ->and(OwnRevenueBudget::query()->count())->toBe(0)
        ->and(PaymentProcedure::query()->count())->toBe(0)
        ->and(FinanceFolioSequence::query()->count())->toBe(0);

    Storage::disk('local')->assertMissing('u300/imports/source.pdf');
    Storage::disk('public')->assertMissing('u300/technical-sheets/reference-photos/photo.jpg');
    Storage::disk('local')->assertMissing('own-revenue/imports/source.xlsx');
    Storage::disk('local')->assertMissing('finance/expense-classifications/imports/source.xlsx');
});

function createU300Program(): U300Program
{
    return U300Program::factory()->create([
        'fiscal_year' => 2026,
        'name' => 'Programa U300 de prueba',
        'objective' => 'Probar el aislamiento del reinicio.',
        'justification' => 'Registro testigo de U300.',
        'responsible_name' => 'Responsable de prueba',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Lic.',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
}

function createExpenseClassification(): ExpenseClassification
{
    return ExpenseClassification::factory()->create([
        'fiscal_year' => 2026,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales y suministros',
        'concept_code' => '2100',
        'concept_name' => 'Materiales de administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Materiales y útiles',
        'specific_item_code' => '21101',
        'specific_item_name' => 'Materiales de oficina',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}
