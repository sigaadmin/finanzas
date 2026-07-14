<?php

use App\Actions\Finance\OwnRevenue\Imports\AssignOwnRevenueImportFormat;
use App\Actions\Finance\OwnRevenue\Imports\DiscardOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

function ownRevenueUploadUser(UserRole $role): User
{
    $email = sprintf('upload-%s-%s@crenfcp.edu.mx', $role->value, fake()->uuid());
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function ownRevenueUploadFixture(OwnRevenueImportFormat|string $format): string
{
    $sheets = match ($format) {
        OwnRevenueImportFormat::Abpre => ['ABRPRE-01' => [6 => [
            'A' => 'Clave Unidad Responsable', 'B' => 'Partida', 'C' => 'Enero', 'D' => 'Febrero',
            'E' => 'Marzo', 'F' => 'Abril', 'G' => 'Mayo', 'H' => 'Junio', 'I' => 'Julio', 'J' => 'Agosto',
            'K' => 'Septiembre', 'L' => 'Octubre', 'M' => 'Noviembre', 'N' => 'Diciembre', 'O' => 'Anual',
        ]]],
        OwnRevenueImportFormat::Fuel => ['FICHA' => [3 => [
            'A' => 'FECHAS DE LA COMISION', 'B' => 'MODELO DE VEHÍCULO', 'C' => 'RECORRIDO', 'D' => 'IMPORTE',
        ]]],
        'ambiguous' => ['FICHA' => [
            3 => ['A' => 'FECHAS DE LA COMISION', 'B' => 'MODELO DE VEHÍCULO', 'C' => 'RECORRIDO', 'D' => 'IMPORTE'],
            4 => ['A' => 'FECHAS DE LA COMISION', 'B' => 'NOMBRE DE PERSONAL COMISIONADO', 'C' => 'COSTO UMA', 'D' => 'VIATICOS', 'E' => 'HOSPEDAJE'],
        ]],
    };

    return OwnRevenueXlsxFixtureFactory::create($sheets);
}

function ownRevenueUploadedFile(string $path, string $name = 'presupuesto.xlsx'): UploadedFile
{
    return new UploadedFile(
        $path,
        $name,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

function ownRevenueHighlyCompressibleUploadFixture(): string
{
    $fixture = ownRevenueUploadFixture(OwnRevenueImportFormat::Abpre);
    $zip = new ZipArchive;

    if ($zip->open($fixture) !== true) {
        throw new RuntimeException('No se pudo abrir el fixture XLSX.');
    }

    $zip->addFromString('xl/media/padding.bin', str_repeat('A', 1024 * 1024));
    $zip->close();

    return $fixture;
}

test('manager reuses one open session and stores an XLSX privately', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $first = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $file = app(UploadOwnRevenueImportFile::class)->handle(
        $session,
        $manager,
        ownRevenueUploadedFile(ownRevenueUploadFixture(OwnRevenueImportFormat::Abpre), 'ABPRE 2027.xlsx'),
        false,
    );

    expect($first->is($session))->toBeTrue()
        ->and($budget->importSessions()->count())->toBe(1)
        ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
        ->and($file->version_number)->toBe(1)
        ->and($file->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($file->storage_path);
});

test('highly compressible XLSX uploads return a file validation error', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.store', $budget), [
            'file' => ownRevenueUploadedFile(ownRevenueHighlyCompressibleUploadFixture()),
        ])
        ->assertSessionHasErrors('file');

    expect($budget->importFiles()->count())->toBe(0);
});

test('duplicate hashes require explicit reanalysis and then create the next version', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $fixture = ownRevenueUploadFixture(OwnRevenueImportFormat::Abpre);
    $action = app(UploadOwnRevenueImportFile::class);
    $first = $action->handle($session, $manager, ownRevenueUploadedFile($fixture), false);

    expect(fn () => $action->handle($session, $manager, ownRevenueUploadedFile($fixture), false))
        ->toThrow(ValidationException::class);

    $second = $action->handle($session, $manager, ownRevenueUploadedFile($fixture), true);

    expect($second->version_number)->toBe(2)
        ->and($second->sha256)->toBe($first->sha256);
});

test('consultation roles cannot start or upload import sessions', function (UserRole $role) {
    Storage::fake('local');
    $user = ownRevenueUploadUser($role);
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);

    expect(fn () => app(StartOwnRevenueImportSession::class)->handle($budget, $user))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(UploadOwnRevenueImportFile::class)->handle(
            $session,
            $user,
            ownRevenueUploadedFile(ownRevenueUploadFixture(OwnRevenueImportFormat::Abpre)),
            false,
        ))->toThrow(AuthorizationException::class);
})->with([UserRole::FinanceAssistant, UserRole::FinanceAuditor]);

test('ambiguous uploads require correction and assignment recalculates their format version', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $file = app(UploadOwnRevenueImportFile::class)->handle(
        $session,
        $manager,
        ownRevenueUploadedFile(ownRevenueUploadFixture('ambiguous')),
        false,
    );

    expect($file->format)->toBeNull()
        ->and($file->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($file->version_number)->toBe(1);

    $assigned = app(AssignOwnRevenueImportFormat::class)->handle($file, $manager, OwnRevenueImportFormat::Fuel);

    expect($assigned->format)->toBe(OwnRevenueImportFormat::Fuel)
        ->and($assigned->version_number)->toBe(1)
        ->and($assigned->status)->toBe(OwnRevenueImportFileStatus::ParserPending);
});

test('recognized secondary formats remain pending their parser', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $file = app(UploadOwnRevenueImportFile::class)->handle(
        $session,
        $manager,
        ownRevenueUploadedFile(ownRevenueUploadFixture(OwnRevenueImportFormat::Fuel)),
        false,
    );

    expect($file->format)->toBe(OwnRevenueImportFormat::Fuel)
        ->and($file->status)->toBe(OwnRevenueImportFileStatus::ParserPending);
});

test('discard preserves evidence but confirmed files cannot be discarded', function () {
    Storage::fake('local');
    $manager = ownRevenueUploadUser(UserRole::FinanceManager);
    $budget = OwnRevenueBudget::factory()->create();
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $action = app(UploadOwnRevenueImportFile::class);
    $discard = app(DiscardOwnRevenueImportFile::class);
    $file = $action->handle($session, $manager, ownRevenueUploadedFile(ownRevenueUploadFixture(OwnRevenueImportFormat::Abpre)), false);
    $discarded = $discard->handle($file, $manager);

    expect($discarded->status)->toBe(OwnRevenueImportFileStatus::Discarded);
    Storage::disk('local')->assertExists($discarded->storage_path);

    $confirmed = $action->handle($session, $manager, ownRevenueUploadedFile(ownRevenueUploadFixture(OwnRevenueImportFormat::Fuel)), false);
    $confirmed->update(['status' => OwnRevenueImportFileStatus::Confirmed]);

    expect(fn () => $discard->handle($confirmed, $manager))->toThrow(ValidationException::class);
});
