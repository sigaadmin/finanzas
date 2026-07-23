<?php

use App\Actions\Finance\U300\CreateU300BackupArchive;
use App\Actions\Finance\U300\InspectU300BackupArchive;
use App\Actions\Finance\U300\RestoreU300BackupArchive;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\U300\U300BackupArchive;
use App\Models\Finance\U300\U300BackupOperation;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

test('a finance manager may upload a U300 backup for preview', function () {
    $user = u300BackupUser(UserRole::FinanceManager);

    $this->actingAs($user)
        ->post(route('finance.u300.backups.preview'), ['archive' => UploadedFile::fake()->create('u300.zip', 10, 'application/zip')])
        ->assertSessionHasNoErrors();
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

test('a U300 backup includes only referenced technical sheet photos', function () {
    Storage::fake('local');
    Storage::fake('public');
    $user = u300BackupUser(UserRole::FinanceManager);
    $program = U300Program::query()->create([
        'imported_by' => $user->id,
        'fiscal_year' => 2026,
        'name' => 'Proyecto U300 2026',
        'objective' => 'Objetivo.',
        'justification' => 'Justificación.',
        'requested_total_cents' => 100,
        'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección',
        'responsible_academic_degree' => 'Maestría',
        'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
    $version = $program->budgetVersions()->create(['created_by' => $user->id, 'kind' => 'adjusted', 'name' => 'Adecuación', 'status' => 'draft', 'total_cents' => 100]);
    $project = $program->projects()->create(['number' => '1', 'name' => 'Proyecto']);
    $goal = $project->goals()->create(['number' => '1.1', 'description' => 'Meta']);
    $action = $goal->actions()->create(['number' => '1.1.1', 'name' => 'Acción']);
    $line = $version->budgetLines()->create(['u300_action_id' => $action->id, 'amount_cents' => 100]);
    $line->technicalSheet()->create(['goods_profile' => [['reference_photo_path' => 'storage/u300/technical-sheets/reference-photos/evidencia.jpg']]]);
    Storage::disk('public')->put('u300/technical-sheets/reference-photos/evidencia.jpg', 'FOTO');
    Storage::disk('public')->put('u300/technical-sheets/reference-photos/ajena.jpg', 'AJENA');

    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($archive->path));

    expect($zip->getFromName('files/technical-sheets/evidencia.jpg'))->toBe('FOTO')
        ->and($zip->locateName('files/technical-sheets/ajena.jpg'))->toBeFalse();
    $zip->close();

    Storage::disk('public')->delete('u300/technical-sheets/reference-photos/evidencia.jpg');
    app(RestoreU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path), $user);

    expect(Storage::disk('public')->get('u300/technical-sheets/reference-photos/evidencia.jpg'))->toBe('FOTO');
});

test('a valid U300 archive produces a restore preview', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    $program = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2026, 'name' => 'Proyecto', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');

    $preview = app(InspectU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path));

    expect($preview['fiscal_year'])->toBe(2026)
        ->and($preview['files_count'])->toBe(1);
});

test('a tampered U300 archive is rejected before restoration', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    $program = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2026, 'name' => 'Proyecto', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($archive->path));
    $zip->addFromString('data/program.json', '{"altered":true}');
    $zip->close();

    expect(fn () => app(InspectU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path)))
        ->toThrow(RuntimeException::class);
});

test('restoring a U300 archive replaces only its fiscal year', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    $source = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2026, 'name' => 'Respaldo 2026', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
    $version = $source->budgetVersions()->create(['created_by' => $user->id, 'kind' => 'original_requested', 'name' => 'Original', 'status' => 'confirmed', 'total_cents' => 100]);
    $project = $source->projects()->create(['number' => '1', 'name' => 'Proyecto']);
    $goal = $project->goals()->create(['number' => '1.1', 'description' => 'Meta']);
    $action = $goal->actions()->create(['number' => '1.1.1', 'name' => 'Acción']);
    $action->requestedItems()->create(['u300_budget_version_id' => $version->id, 'expense_concept' => 'Concepto', 'expense_item' => 'Partida', 'period' => 1, 'quantity' => 1, 'unit_price_cents' => 100, 'total_cents' => 100]);
    $cog = ExpenseClassification::query()->create(['fiscal_year' => 2026, 'chapter_code' => '3000', 'chapter_name' => 'Servicios', 'concept_code' => '3700', 'concept_name' => 'Traslados', 'generic_item_code' => '3750', 'generic_item_name' => 'Viáticos', 'specific_item_code' => '37501', 'specific_item_name' => 'Viáticos nacionales', 'expense_type_code' => '1', 'expense_type_name' => 'Corriente']);
    $version->budgetLines()->create(['u300_action_id' => $action->id, 'expense_classification_id' => $cog->id, 'amount_cents' => 100, 'exercise_month' => 'ENE', 'description' => 'Partida']);
    $archive = app(CreateU300BackupArchive::class)->handle($source, $user, 'manual');
    U300Program::query()->whereKey($source)->update(['name' => 'Datos actuales']);
    $otherYear = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2027, 'name' => 'Conservar 2027', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);

    app(RestoreU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path), $user);

    expect(U300Program::query()->where('fiscal_year', 2026)->sole()->name)->toBe('Respaldo 2026')
        ->and(U300Program::query()->where('fiscal_year', 2026)->sole()->projects()->count())->toBe(1)
        ->and(U300Program::query()->where('fiscal_year', 2026)->sole()->budgetVersions()->first()->requestedItems()->count())->toBe(1)
        ->and(U300Program::query()->where('fiscal_year', 2026)->sole()->budgetVersions()->first()->budgetLines()->count())->toBe(1)
        ->and(U300Program::query()->where('fiscal_year', 2026)->sole()->budgetVersions()->first()->budgetLines()->first()->expenseClassification->specific_item_code)->toBe('37501')
        ->and(U300Program::query()->find($otherYear->id))->not->toBeNull();
    expect(U300BackupArchive::query()->where('fiscal_year', 2026)->where('kind', 'pre_restore')->exists())->toBeTrue();
    expect(U300BackupOperation::query()->where('fiscal_year', 2026)->where('type', 'restored')->where('status', 'succeeded')->exists())->toBeTrue();
});

test('restoring a U300 archive recreates its source file', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    Storage::disk('local')->put('u300/imports/original.pdf', 'ORIGINAL');
    $program = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2026, 'name' => 'Proyecto', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx', 'source_filename' => 'original.pdf', 'source_path' => 'u300/imports/original.pdf',
    ]);
    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    Storage::disk('local')->delete('u300/imports/original.pdf');

    app(RestoreU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path), $user);

    Storage::disk('local')->assertExists('u300/imports/original.pdf');
    expect(Storage::disk('local')->get('u300/imports/original.pdf'))->toBe('ORIGINAL');
});

test('a missing COG prevents restoration and records the failure', function () {
    Storage::fake('local');
    $user = u300BackupUser(UserRole::FinanceManager);
    $program = U300Program::query()->create([
        'imported_by' => $user->id, 'fiscal_year' => 2026, 'name' => 'Datos actuales', 'objective' => 'Objetivo.',
        'justification' => 'Justificación.', 'requested_total_cents' => 100, 'responsible_name' => 'Responsable',
        'responsible_position' => 'Dirección', 'responsible_academic_degree' => 'Maestría', 'responsible_phone' => '9830000000',
        'responsible_email' => 'responsable@crenfcp.edu.mx',
    ]);
    $version = $program->budgetVersions()->create(['created_by' => $user->id, 'kind' => 'adjusted', 'name' => 'Adecuación', 'status' => 'draft', 'total_cents' => 100]);
    $project = $program->projects()->create(['number' => '1', 'name' => 'Proyecto']);
    $goal = $project->goals()->create(['number' => '1.1', 'description' => 'Meta']);
    $action = $goal->actions()->create(['number' => '1.1.1', 'name' => 'Acción']);
    $cog = ExpenseClassification::query()->create(['fiscal_year' => 2026, 'chapter_code' => '3000', 'chapter_name' => 'Servicios', 'concept_code' => '3700', 'concept_name' => 'Traslados', 'generic_item_code' => '3750', 'generic_item_name' => 'Viáticos', 'specific_item_code' => '37501', 'specific_item_name' => 'Viáticos nacionales', 'expense_type_code' => '1', 'expense_type_name' => 'Corriente']);
    $version->budgetLines()->create(['u300_action_id' => $action->id, 'expense_classification_id' => $cog->id, 'amount_cents' => 100]);
    $archive = app(CreateU300BackupArchive::class)->handle($program, $user, 'manual');
    $cog->delete();

    expect(fn () => app(RestoreU300BackupArchive::class)->handle(Storage::disk('local')->path($archive->path), $user))
        ->toThrow(RuntimeException::class);
    expect(U300Program::query()->where('fiscal_year', 2026)->sole()->name)->toBe('Datos actuales')
        ->and(U300BackupOperation::query()->where('fiscal_year', 2026)->where('type', 'restored')->where('status', 'failed')->exists())->toBeTrue();
});
