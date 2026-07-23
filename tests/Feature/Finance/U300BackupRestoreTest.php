<?php

use App\Actions\Finance\U300\CreateU300BackupArchive;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\U300\U300BackupArchive;
use App\Models\Finance\U300\U300BackupOperation;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function u300BackupUser(UserRole $role): User
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

test('only authorized finance roles may manage U300 backups', function () {
    expect(u300BackupUser(UserRole::FinanceAssistant)->canManageU300Backups())->toBeFalse()
        ->and(u300BackupUser(UserRole::FinanceAuditor)->canManageU300Backups())->toBeFalse()
        ->and(u300BackupUser(UserRole::FinanceManager)->canManageU300Backups())->toBeTrue()
        ->and(u300BackupUser(UserRole::Admin)->canManageU300Backups())->toBeTrue();
});

test('backup archives and operations preserve their audit metadata', function () {
    $user = u300BackupUser(UserRole::FinanceManager);
    $archive = U300BackupArchive::query()->create([
        'fiscal_year' => 2026,
        'kind' => 'manual',
        'disk' => 'local',
        'path' => 'u300/backups/backup.zip',
        'original_filename' => 'u300-2026.zip',
        'size_bytes' => 128,
        'sha256' => str_repeat('a', 64),
        'manifest' => ['format_version' => 1],
        'created_by' => $user->id,
    ]);

    $operation = U300BackupOperation::query()->create([
        'u300_backup_archive_id' => $archive->id,
        'fiscal_year' => 2026,
        'type' => 'generated',
        'status' => 'succeeded',
        'performed_by' => $user->id,
        'details' => ['programs' => 1],
    ]);

    expect($archive->manifest)->toBe(['format_version' => 1])
        ->and($operation->details)->toBe(['programs' => 1]);
});

test('a U300 backup contains a versioned manifest and the program data', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    $program = U300Program::query()->create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => 'Proyecto U300 2026',
        'objective' => 'Objetivo.',
        'justification' => 'Justificación.',
        'requested_total_cents' => 10000,
        'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Maestría',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);

    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');

    Storage::disk('local')->assertExists($archive->path);
    expect($archive->manifest['format_version'])->toBe(1)
        ->and($archive->manifest['fiscal_year'])->toBe(2026)
        ->and($archive->manifest['files'])->toHaveKey('data/program.json');
});

test('a U300 backup includes its original source document', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    Storage::disk('local')->put('u300/imports/proyecto-u300.pdf', 'PDF U300');
    $program = U300Program::query()->create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => 'Proyecto U300 2026',
        'objective' => 'Objetivo.',
        'justification' => 'Justificación.',
        'requested_total_cents' => 10000,
        'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Maestría',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
        'source_filename' => 'proyecto-u300.pdf',
        'source_path' => 'u300/imports/proyecto-u300.pdf',
    ]);

    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($archive->path));

    expect($zip->getFromName('files/source/proyecto-u300.pdf'))->toBe('PDF U300');
    $zip->close();
});
