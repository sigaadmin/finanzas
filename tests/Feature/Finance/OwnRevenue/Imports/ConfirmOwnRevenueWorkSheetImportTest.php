<?php

use App\Actions\Finance\OwnRevenue\Imports\CaptureOwnRevenueImportAnalysisSnapshot;
use App\Actions\Finance\OwnRevenue\Imports\ConfirmOwnRevenueWorkSheetImport;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreMonth;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportOrigin;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function workSheetConfirmationUser(UserRole $role = UserRole::FinanceManager): User
{
    $email = 'work-sheet-confirmation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => $role, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function workSheetConfirmationCog(int $year, string $code): ExpenseClassification
{
    return ExpenseClassification::query()->firstOrCreate([
        'fiscal_year' => $year,
        'specific_item_code' => $code,
    ], [
        'chapter_code' => substr($code, 0, 1).'000',
        'chapter_name' => 'Capítulo '.substr($code, 0, 1).'000',
        'concept_code' => substr($code, 0, 2).'00',
        'concept_name' => 'Concepto '.substr($code, 0, 2).'00',
        'generic_item_code' => substr($code, 0, 4).'0',
        'generic_item_name' => 'Genérica '.substr($code, 0, 4).'0',
        'specific_item_name' => 'Partida '.$code,
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}

/**
 * @return array{file:OwnRevenueImportFile,lines:list<array<string,mixed>>,months:list<array<string,mixed>>}
 */
function confirmedAbpreForWorkSheetConfirmation(OwnRevenueBudget $budget, User $manager, array $classifications): array
{
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'detected_format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_by' => $manager->id,
        'confirmed_at' => now()->subMinute(),
        'version_number' => ((int) OwnRevenueImportFile::query()
            ->where('own_revenue_budget_id', $budget->id)
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->max('version_number')) + 1,
    ]);

    foreach ($classifications as $index => $classification) {
        $line = OwnRevenueAbpreLine::factory()->create([
            'own_revenue_budget_id' => $budget->id,
            'own_revenue_import_file_id' => $file->id,
            'expense_classification_id' => $classification->id,
            'specific_item_code' => $classification->specific_item_code,
            'annual_amount_cents' => 1200 + $index,
            'sort_order' => $index,
        ]);
        foreach (range(1, 12) as $month) {
            OwnRevenueAbpreMonth::factory()->create([
                'own_revenue_abpre_line_id' => $line->id,
                'month' => $month,
                'amount_cents' => 100,
            ]);
        }
    }

    return [
        'file' => $file,
        'lines' => OwnRevenueAbpreLine::query()->where('own_revenue_import_file_id', $file->id)->orderBy('id')->get()->map->getAttributes()->all(),
        'months' => OwnRevenueAbpreMonth::query()->whereIn('own_revenue_abpre_line_id', $file->abpreLines()->pluck('id'))->orderBy('id')->get()->map->getAttributes()->all(),
    ];
}

/**
 * @param  list<array{activity_code:string,activity_name:string,item_name:string,specific_item_code:string,months:array<int,string>,source_rows:list<int>}>  $lines
 * @return array{file:OwnRevenueImportFile,abpre:array{file:OwnRevenueImportFile,lines:list<array<string,mixed>>,months:list<array<string,mixed>>}}
 */
function readyWorkSheetConfirmationFile(User $manager, ?OwnRevenueBudget $budget = null, ?array $lines = null): array
{
    $budget ??= OwnRevenueBudget::factory()->create();
    $lines ??= [
        [
            'activity_code' => 'A03-A01', 'activity_name' => 'Investigación', 'item_name' => 'Papelería',
            'specific_item_code' => '21101', 'months' => [1 => '101', 2 => '202', 3 => '0', 4 => '0', 5 => '0', 6 => '0', 7 => '0', 8 => '0', 9 => '0', 10 => '0', 11 => '0', 12 => '0'],
            'source_rows' => [5, 6],
        ],
        [
            'activity_code' => 'A03-A02', 'activity_name' => 'Difusión', 'item_name' => 'Combustible',
            'specific_item_code' => '26101', 'months' => [1 => '0', 2 => '0', 3 => '0', 4 => '0', 5 => '0', 6 => '0', 7 => '0', 8 => '0', 9 => '0', 10 => '0', 11 => '0', 12 => '999'],
            'source_rows' => [7],
        ],
    ];

    $activities = collect($lines)->unique('activity_code')->mapWithKeys(function (array $line) use ($budget): array {
        $activity = $budget->activities()->firstOrCreate(
            ['code' => $line['activity_code']],
            ['name' => $line['activity_name']],
        );

        return [$line['activity_code'] => $activity];
    });
    $classifications = collect($lines)->unique('specific_item_code')->mapWithKeys(
        fn (array $line): array => [
            $line['specific_item_code'] => workSheetConfirmationCog($budget->fiscal_year, $line['specific_item_code']),
        ],
    );
    $abpre = confirmedAbpreForWorkSheetConfirmation($budget, $manager, $classifications->values()->all());
    $revision = (string) Str::uuid();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'detected_format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Ready,
        'analysis_token' => null,
        'analysis_revision' => $revision,
        'abpre_import_file_id_at_analysis' => $abpre['file']->id,
        'budget_updated_at_at_analysis' => $budget->updated_at,
        'analyzed_at' => now(),
        'version_number' => ((int) OwnRevenueImportFile::query()
            ->where('own_revenue_budget_id', $budget->id)
            ->where('format', OwnRevenueImportFormat::WorkSheet)
            ->max('version_number')) + 1,
    ]);
    Storage::disk('local')->put($file->storage_path, 'work-sheet-confirmation');
    $file->forceFill(['sha256' => hash('sha256', 'work-sheet-confirmation')])->save();

    foreach ($lines as $index => $lineData) {
        foreach ($lineData['source_rows'] as $sourceRow) {
            $sourcePayload = [
                'actividad' => ['coordinate' => 'A'.$sourceRow, 'value' => $lineData['activity_code'], 'formula' => null],
                'partida' => ['coordinate' => 'C'.$sourceRow, 'value' => $lineData['specific_item_code'], 'formula' => null],
                'enero' => ['coordinate' => 'G'.$sourceRow, 'value' => '1.01', 'formula' => '=1.01'],
                'diciembre' => ['coordinate' => 'R'.$sourceRow, 'value' => '9.99', 'formula' => '=SUM(A1:A2)'],
            ];
            $file->rows()->create([
                'sheet_name' => 'HOJA FINAL',
                'row_number' => $sourceRow,
                'row_kind' => 'work_sheet_line',
                'row_hash' => hash('sha256', json_encode($sourcePayload, JSON_THROW_ON_ERROR)),
                'source_payload' => $sourcePayload,
                'normalized_payload' => ['months' => $lineData['months']],
            ]);
        }

        $annual = array_reduce($lineData['months'], fn (int $sum, string $amount): int => $sum + (int) $amount, 0);
        $normalizedPayload = [
            'activityCode' => $lineData['activity_code'],
            'activityName' => $lineData['activity_name'],
            'itemName' => $lineData['item_name'],
            'specificItemCode' => $lineData['specific_item_code'],
            'regionCode' => '02-001',
            'regionName' => 'Felipe Carrillo Puerto',
            'sourceRegions' => [['code' => '04-001', 'name' => 'Chetumal']],
            'months' => $lineData['months'],
            'annualAmountCents' => (string) $annual,
            'sourceRows' => $lineData['source_rows'],
        ];
        $file->rows()->create([
            'sheet_name' => '__normalized_work_sheet__',
            'row_number' => $index + 1,
            'row_kind' => 'work_sheet_normalized_line',
            'row_hash' => hash('sha256', json_encode($normalizedPayload, JSON_THROW_ON_ERROR)),
            'source_payload' => ['source_rows' => $lineData['source_rows']],
            'normalized_payload' => $normalizedPayload,
        ]);
    }

    $fingerprint = app(CaptureOwnRevenueImportAnalysisSnapshot::class)->handle($budget->fresh())->fingerprint;
    $file->forceFill(['analysis_fingerprint' => $fingerprint])->save();

    return ['file' => $file->fresh(), 'abpre' => $abpre];
}

function addRequiredWorkSheetDecision(OwnRevenueImportFile $file, User $manager, string $resolution = 'accepted', ?string $revision = null): OwnRevenueImportIssue
{
    $issue = $file->issues()->create([
        'severity' => OwnRevenueImportIssueSeverity::Warning,
        'code' => 'work_sheet.abpre_mismatch',
        'field' => '21101',
        'message' => 'La partida difiere del ABPRE.',
        'context' => [
            'requires_decision' => true,
            'abpre_import_file_id' => $file->abpre_import_file_id_at_analysis,
        ],
    ]);
    $issue->decisions()->create([
        'current_value' => $issue->context,
        'resolved_value' => [
            'accepted' => $resolution === 'accepted',
            'analysis_revision' => $revision ?? $file->analysis_revision,
        ],
        'resolution' => $resolution,
        'resolved_by' => $manager->id,
        'resolved_at' => now(),
    ]);

    return $issue;
}

test('confirmation persists grouped work sheet lines twelve exact months and auditable source origins', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file, 'abpre' => $abpre] = readyWorkSheetConfirmationFile($manager);
    $abpreFileBefore = $abpre['file']->fresh()->getAttributes();

    $this->actingAs($manager)
        ->post(route('finance.own-revenue.budgets.imports.files.work-sheet.confirm', [
            'budget' => $file->budget,
            'importFile' => $file,
        ]), ['analysis_revision' => $file->analysis_revision])
        ->assertRedirect(route('finance.own-revenue.budgets.imports.files.preview', [
            'budget' => $file->budget,
            'importFile' => $file,
        ]))
        ->assertInertiaFlash('success', 'Hoja de trabajo confirmada correctamente.');

    $lines = OwnRevenueWorkSheetLine::query()->where('own_revenue_import_file_id', $file->id)->orderBy('sort_order')->get();
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($file->fresh()->confirmed_by)->toBe($manager->id)
        ->and($lines)->toHaveCount(2)
        ->and($lines[0]->own_revenue_activity_id)->not->toBeNull()
        ->and($lines[0]->activity_code)->toBe('A03-A01')
        ->and($lines[0]->specific_item_code)->toBe('21101')
        ->and($lines[0]->region_code)->toBe('02-001')
        ->and($lines[0]->annual_amount_cents)->toBe(303)
        ->and($lines[0]->months)->toHaveCount(12)
        ->and($lines[0]->months->pluck('month')->sort()->values()->all())->toBe(range(1, 12))
        ->and($lines[0]->months->sum('amount_cents'))->toBe(303)
        ->and($lines[1]->annual_amount_cents)->toBe(999)
        ->and($lines[0]->origins)->toHaveCount(2)
        ->and($lines[0]->months->firstWhere('month', 1)->origins)->toHaveCount(2);

    $januaryOrigin = $lines[0]->months->firstWhere('month', 1)->origins->first();
    expect($januaryOrigin->field_name)->toBe('month.1')
        ->and($januaryOrigin->row->sheet_name)->toBe('HOJA FINAL')
        ->and($januaryOrigin->row->source_payload['enero'])->toBe([
            'coordinate' => 'G5', 'value' => '1.01', 'formula' => '=1.01',
        ])
        ->and($abpre['file']->fresh()->getAttributes())->toBe($abpreFileBefore)
        ->and($abpre['lines'])->toBe(OwnRevenueAbpreLine::query()->where('own_revenue_import_file_id', $abpre['file']->id)->orderBy('id')->get()->map->getAttributes()->all())
        ->and($abpre['months'])->toBe(OwnRevenueAbpreMonth::query()->whereIn('own_revenue_abpre_line_id', $abpre['file']->abpreLines()->pluck('id'))->orderBy('id')->get()->map->getAttributes()->all());
});

test('all required warnings need a current accepted decision', function (string $state) {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    if ($state !== 'missing') {
        addRequiredWorkSheetDecision(
            $file,
            $manager,
            $state === 'rejected' ? 'rejected' : 'accepted',
            $state === 'stale' ? (string) Str::uuid() : null,
        );
    } else {
        $file->issues()->create([
            'severity' => OwnRevenueImportIssueSeverity::Warning,
            'code' => 'work_sheet.abpre_mismatch',
            'message' => 'Requiere decisión.',
            'context' => ['requires_decision' => true, 'abpre_import_file_id' => $file->abpre_import_file_id_at_analysis],
        ]);
    }

    expect(fn () => app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $file->analysis_revision))
        ->toThrow(ValidationException::class);
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and(OwnRevenueWorkSheetLine::query()->where('own_revenue_import_file_id', $file->id)->exists())->toBeFalse();
})->with(['missing', 'rejected', 'stale']);

test('a current accepted ABPRE mismatch decision allows confirmation', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    addRequiredWorkSheetDecision($file, $manager);

    app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $file->analysis_revision);

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed);
});

test('blocking analysis errors prevent confirmation', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    $file->issues()->create([
        'severity' => OwnRevenueImportIssueSeverity::Error,
        'code' => 'cog.missing_item',
        'message' => 'Partida ausente.',
        'context' => [],
    ]);

    expect(fn () => app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $file->analysis_revision))
        ->toThrow(ValidationException::class);
});

test('confirmation rejects altered files and missing normalized staging', function (string $state) {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    if ($state === 'file') {
        Storage::disk('local')->put($file->storage_path, 'altered-work-sheet');
    } else {
        $file->rows()->where('row_kind', 'work_sheet_normalized_line')->delete();
    }

    expect(fn () => app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $file->analysis_revision))
        ->toThrow(ValidationException::class);
    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($file->workSheetLines()->exists())->toBeFalse();
})->with(['file', 'staging']);

test('confirmation rejects stale analysis UUID and active analysis tokens', function (string $state) {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    if ($state === 'token') {
        $file->update(['analysis_token' => (string) Str::uuid()]);
    }

    $revision = $state === 'uuid' ? (string) Str::uuid() : $file->analysis_revision;
    expect(fn () => app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $revision))
        ->toThrow(ValidationException::class);
})->with(['uuid', 'token']);

test('confirmation rejects a replaced ABPRE and a changed deterministic budget snapshot', function (string $state) {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file, 'abpre' => $abpre] = readyWorkSheetConfirmationFile($manager);
    if ($state === 'abpre') {
        $abpre['file']->update(['status' => OwnRevenueImportFileStatus::Replaced]);
        confirmedAbpreForWorkSheetConfirmation($file->budget, $manager, [
            ExpenseClassification::query()->where('fiscal_year', $file->budget->fiscal_year)->firstOrFail(),
        ]);
    } elseif ($state === 'budget') {
        $file->budget->forceFill(['updated_at' => now()->addSecond()])->save();
    } else {
        $file->budget->activities()->firstOrFail()->update(['name' => 'Actividad modificada']);
    }

    expect(fn () => app(ConfirmOwnRevenueWorkSheetImport::class)->handle($file, $manager, $file->analysis_revision))
        ->toThrow(ValidationException::class);
})->with(['abpre', 'budget', 'catalog']);

test('confirmation enforces authorization tenant format and an open budget', function (string $state) {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    $routeBudget = $file->budget;
    $actingUser = $manager;

    if ($state === 'authorization') {
        $actingUser = workSheetConfirmationUser(UserRole::FinanceAuditor);
    } elseif ($state === 'tenant') {
        $routeBudget = OwnRevenueBudget::factory()->create();
    } elseif ($state === 'format') {
        $file->update(['format' => OwnRevenueImportFormat::Abpre, 'version_number' => 999]);
    } else {
        $file->budget->update(['status' => OwnRevenueBudgetStatus::Closed]);
    }

    $response = $this->actingAs($actingUser)->post(route('finance.own-revenue.budgets.imports.files.work-sheet.confirm', [
        'budget' => $routeBudget,
        'importFile' => $file,
    ]), ['analysis_revision' => $file->analysis_revision]);

    match ($state) {
        'authorization', 'closed' => $response->assertForbidden(),
        'tenant' => $response->assertNotFound(),
        'format' => $response->assertSessionHasErrors('file'),
    };
})->with(['authorization', 'tenant', 'format', 'closed']);

test('replacement preserves the prior confirmed aggregate and leaves exactly one current version', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    $budget = OwnRevenueBudget::factory()->create();
    ['file' => $first] = readyWorkSheetConfirmationFile($manager, $budget);
    $action = app(ConfirmOwnRevenueWorkSheetImport::class);
    $action->handle($first, $manager, $first->analysis_revision);
    $firstAggregate = $first->workSheetLines()->with(['months', 'origins', 'months.origins'])->get()->toArray();

    ['file' => $second] = readyWorkSheetConfirmationFile($manager, $budget, [[
        'activity_code' => 'A03-A01', 'activity_name' => 'Investigación', 'item_name' => 'Papelería nueva',
        'specific_item_code' => '21101', 'months' => array_fill(1, 12, '50'), 'source_rows' => [8],
    ]]);
    $action->handle($second, $manager, $second->analysis_revision);

    expect($first->fresh()->status)->toBe(OwnRevenueImportFileStatus::Replaced)
        ->and($first->fresh()->replaced_by_file_id)->toBe($second->id)
        ->and($second->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and(OwnRevenueImportFile::query()->where('own_revenue_budget_id', $budget->id)->where('format', OwnRevenueImportFormat::WorkSheet)->where('status', OwnRevenueImportFileStatus::Confirmed)->count())->toBe(1)
        ->and($first->workSheetLines()->with(['months', 'origins', 'months.origins'])->get()->toArray())->toBe($firstAggregate);
});

test('the same file and a duplicated obsolete request cannot be confirmed twice', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);
    $action = app(ConfirmOwnRevenueWorkSheetImport::class);
    $action->handle($file, $manager, $file->analysis_revision);

    expect(fn () => $action->handle($file->fresh(), $manager, $file->analysis_revision))
        ->toThrow(ValidationException::class);
    expect($file->workSheetLines()->count())->toBe(2);
});

test('a failure after replacement rolls back the previous version and all new aggregates', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    $budget = OwnRevenueBudget::factory()->create();
    ['file' => $first] = readyWorkSheetConfirmationFile($manager, $budget);
    $action = app(ConfirmOwnRevenueWorkSheetImport::class);
    $action->handle($first, $manager, $first->analysis_revision);
    ['file' => $second] = readyWorkSheetConfirmationFile($manager, $budget);
    $throw = true;
    OwnRevenueImportFile::updating(function (OwnRevenueImportFile $updating) use ($second, &$throw): void {
        if ($throw && $updating->is($second) && $updating->status === OwnRevenueImportFileStatus::Confirmed) {
            $throw = false;
            throw new RuntimeException('Fallo inyectado después del reemplazo.');
        }
    });

    expect(fn () => $action->handle($second, $manager, $second->analysis_revision))
        ->toThrow(RuntimeException::class, 'Fallo inyectado');

    expect($first->fresh()->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($first->fresh()->replaced_by_file_id)->toBeNull()
        ->and($second->fresh()->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($second->workSheetLines()->exists())->toBeFalse()
        ->and(OwnRevenueImportOrigin::query()->whereHasMorph('originable', [OwnRevenueWorkSheetLine::class], fn ($query) => $query->where('own_revenue_import_file_id', $second->id))->exists())->toBeFalse();
});

test('confirmation request requires a UUID analysis revision', function () {
    Storage::fake('local');
    $manager = workSheetConfirmationUser();
    ['file' => $file] = readyWorkSheetConfirmationFile($manager);

    $this->actingAs($manager)->post(route('finance.own-revenue.budgets.imports.files.work-sheet.confirm', [
        'budget' => $file->budget,
        'importFile' => $file,
    ]), ['analysis_revision' => 'invalid'])->assertSessionHasErrors('analysis_revision');
});
