<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\ConfirmOwnRevenueAbpreImport;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

function confirmationUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'confirmation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function confirmationFixture(string $partida = '21101'): string
{
    return OwnRevenueXlsxFixtureFactory::create([
        'ABRPRE-01' => [
            2 => ['A' => 'Proyecto de Presupuesto 2027'],
            6 => [
                'A' => 'Clave Unidad Responsable', 'B' => 'Nombre de Unidad Responsable', 'C' => 'Programa Presupuestario',
                'D' => 'Nombre Programa Presupuestario', 'E' => 'Clave Componente', 'F' => 'Nombre Componente',
                'G' => 'Clave Actividad', 'H' => 'Nombre Actividad', 'I' => 'Clave Región', 'J' => 'Nombre de la Región',
                'K' => 'Concepto Especifico del Gasto', 'L' => 'Partida', 'M' => 'Enero', 'N' => 'Febrero',
                'O' => 'Marzo', 'P' => 'Abril', 'Q' => 'Mayo', 'R' => 'Junio', 'S' => 'Julio', 'T' => 'Agosto',
                'U' => 'Septiembre', 'V' => 'Octubre', 'W' => 'Noviembre', 'X' => 'Diciembre', 'Y' => 'Anual',
            ],
            7 => [
                'A' => '2330', 'B' => 'Dirección', 'C' => 'E016', 'D' => 'Formación docente', 'E' => 'C01',
                'F' => 'Servicio educativo', 'G' => 'A03', 'H' => 'Prestación del servicio', 'I' => '02-001',
                'J' => 'Felipe Carrillo Puerto', 'K' => '2100', 'L' => $partida, 'M' => '0', 'N' => '0',
                'O' => '0', 'P' => '1050', 'Q' => '0', 'R' => '0', 'S' => '0', 'T' => '0', 'U' => '0',
                'V' => '0', 'W' => '0', 'X' => '0', 'Y' => '1050',
            ],
        ],
        'Formato Justificación Partidas' => [
            7 => ['B' => 'Unidad Responble', 'C' => 'Capítulo', 'D' => 'Descripción Capítulo', 'E' => 'Partida', 'F' => 'Descripción Partida', 'G' => 'Programa Prresupuestario', 'H' => 'Componente', 'I' => 'Impacto en Metas', 'J' => 'Justificación'],
            8 => ['B' => 'Dirección', 'C' => '2000', 'D' => 'Materiales', 'E' => $partida, 'F' => 'Papelería', 'G' => 'E016', 'H' => 'Servicio educativo', 'I' => 'Impacto', 'J' => 'Necesario'],
        ],
    ]);
}

function confirmationCog(int $year, string $code = '21101'): ExpenseClassification
{
    return ExpenseClassification::query()->firstOrCreate(['fiscal_year' => $year, 'specific_item_code' => $code], [
        'chapter_code' => '2000', 'chapter_name' => 'Materiales',
        'concept_code' => '2100', 'concept_name' => 'Administración', 'generic_item_code' => '21100',
        'generic_item_name' => 'Oficina', 'specific_item_code' => $code, 'specific_item_name' => 'Papelería',
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
}

function readyConfirmationFile(User $manager, ?OwnRevenueBudget $budget = null): mixed
{
    $budget ??= OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'responsible_unit_code' => '2330']);
    confirmationCog($budget->fiscal_year);
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $fixture = confirmationFixture();
    $upload = new UploadedFile($fixture, 'ABPRE.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $file = app(UploadOwnRevenueImportFile::class)->handle(
        $session,
        $manager,
        $upload,
        $budget->importFiles()->exists(),
    );

    return app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);
}

test('confirmation creates immutable ABPRE lines months justifications and origins', function () {
    Storage::fake('local');
    $manager = confirmationUser();
    $file = readyConfirmationFile($manager);

    app(ConfirmOwnRevenueAbpreImport::class)->handle($file, $manager, []);

    $line = OwnRevenueAbpreLine::query()->sole();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($line->months)->toHaveCount(12)
        ->and($line->origins)->not->toBeEmpty()
        ->and($line->annual_amount_cents)->toBe(105000)
        ->and(OwnRevenueAbpreJustification::query()->count())->toBe(1);
});

test('confirmation is idempotent and replaces the previously confirmed version', function () {
    Storage::fake('local');
    $manager = confirmationUser();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'responsible_unit_code' => '2330']);
    $first = readyConfirmationFile($manager, $budget);
    $action = app(ConfirmOwnRevenueAbpreImport::class);
    $action->handle($first, $manager, []);
    $action->handle($first->fresh(), $manager, []);

    $second = readyConfirmationFile($manager, $budget);
    $action->handle($second, $manager, []);

    expect($first->fresh()->status)->toBe(OwnRevenueImportFileStatus::Replaced)
        ->and($first->fresh()->replaced_by_file_id)->toBe($second->id)
        ->and(OwnRevenueAbpreLine::query()->count())->toBe(2);
});

test('required warnings need an explicit decision', function () {
    Storage::fake('local');
    $manager = confirmationUser();
    $file = readyConfirmationFile($manager);
    $issue = $file->issues()->create([
        'severity' => OwnRevenueImportIssueSeverity::Warning,
        'code' => 'region.normalized',
        'field' => 'region_code',
        'message' => 'Región normalizada.',
        'context' => [],
    ]);
    $action = app(ConfirmOwnRevenueAbpreImport::class);

    expect(fn () => $action->handle($file, $manager, []))->toThrow(ValidationException::class);

    $action->handle($file->fresh(), $manager, [[
        'issue_id' => $issue->id,
        'resolution' => 'manual',
        'resolved_value' => true,
        'justification' => 'Se acepta la región institucional.',
    ]]);

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($issue->decisions()->count())->toBe(1);
});

test('confirmation rejects a budget changed after analysis', function () {
    Storage::fake('local');
    $manager = confirmationUser();
    $file = readyConfirmationFile($manager);
    $file->budget->forceFill([
        'institution_name' => 'Institución modificada',
        'updated_at' => now()->addSecond(),
    ])->save();

    expect(fn () => app(ConfirmOwnRevenueAbpreImport::class)->handle($file, $manager, []))
        ->toThrow(ValidationException::class);
});
