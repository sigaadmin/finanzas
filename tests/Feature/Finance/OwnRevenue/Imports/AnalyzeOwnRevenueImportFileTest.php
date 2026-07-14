<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\AssignOwnRevenueImportFormat;
use App\Actions\Finance\OwnRevenue\Imports\DiscardOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Data\Finance\OwnRevenue\Imports\AbpreAnalysis;
use App\Data\Finance\OwnRevenue\Imports\ImportIssueData;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportDecision;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';
require_once __DIR__.'/../../../../Unit/Finance/OwnRevenue/Imports/AbpreWorkbookParserTest.php';

function analyzeImportUser(): User
{
    $email = 'analyze-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => UserRole::FinanceManager, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function analyzeCog(int $year, string $code = '21101'): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $year, 'chapter_code' => '2000', 'chapter_name' => 'Materiales',
        'concept_code' => '2100', 'concept_name' => 'Administración', 'generic_item_code' => '21100',
        'generic_item_name' => 'Oficina', 'specific_item_code' => $code, 'specific_item_name' => 'Papelería',
        'expense_type_code' => '1', 'expense_type_name' => 'Gasto corriente',
    ]);
}

function uploadedAbpreForAnalysis(User $manager, OwnRevenueBudget $budget): mixed
{
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $fixture = abpreParserFixture();
    $upload = new UploadedFile($fixture, 'ABPRE.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    return app(UploadOwnRevenueImportFile::class)->handle($session, $manager, $upload, false);
}

test('analysis replaces staging and never creates confirmed ABPRE lines', function () {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027, 'responsible_unit_code' => '2330']);
    analyzeCog(2027);
    $file = uploadedAbpreForAnalysis($manager, $budget);

    app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($file->fresh()->analysis_token)->toBeNull()
        ->and($file->rows()->count())->toBeGreaterThan(0)
        ->and($file->issues()->where('severity', 'error')->count())->toBeGreaterThan(0)
        ->and(OwnRevenueAbpreLine::query()->count())->toBe(0);

    $this->withoutVite();
    $this->actingAs($manager)
        ->get(route('finance.own-revenue.budgets.imports.show', $budget))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('preview.data.0.sourceRegions', [
                ['code' => '04-001', 'name' => 'OTRA REGIÓN'],
                ['code' => '03-002', 'name' => 'REGIÓN DOS'],
            ])
            ->where('preview.data.0.regionCode', '02-001')
            ->where('preview.data.0.regionName', 'Felipe Carrillo Puerto'));

    $rowCount = $file->rows()->count();
    app(AnalyzeOwnRevenueImportFile::class)->handle($file->fresh(), $manager);

    expect($file->rows()->count())->toBe($rowCount);
});

test('failed analysis clears attempt ownership', function () {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create();
    $contents = 'failed analysis workbook';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(AbpreWorkbookParser::class);
    $parser->shouldReceive('parse')->once()->andThrow(new RuntimeException('Controlled parser failure'));

    $result = (new AnalyzeOwnRevenueImportFile($reader, $parser))->handle($file, $manager);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::Failed)
        ->and($result->analysis_token)->toBeNull()
        ->and($result->issues()->sole()->code)->toBe('analysis.failed');
});

test('analysis with no importable ABPRE lines requires correction', function () {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create();
    $contents = 'workbook without importable lines';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(AbpreWorkbookParser::class);
    $parser->shouldReceive('parse')->once()->andReturn(new AbpreAnalysis(
        [],
        [],
        [new ImportIssueData(
            OwnRevenueImportIssueSeverity::Warning,
            'abpre.other_unit',
            'responsible_unit_code',
            'La fila corresponde a otra unidad responsable y no será importada.',
        )],
        [],
    ));

    $result = (new AnalyzeOwnRevenueImportFile($reader, $parser))->handle($file, $manager);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($result->issues()->where('code', 'abpre.no_importable_lines')->sole()->severity)
        ->toBe(OwnRevenueImportIssueSeverity::Error);
});

test('unavailable stored files fail validation without mutating import state', function () {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
    ]);
    $row = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
    ]);
    $issue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_import_row_id' => $row->id,
    ]);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldNotReceive('read');
    $parser = Mockery::mock(AbpreWorkbookParser::class);
    $parser->shouldNotReceive('parse');
    $rowSnapshot = $row->getRawOriginal();
    $issueSnapshot = $issue->getRawOriginal();

    try {
        (new AnalyzeOwnRevenueImportFile($reader, $parser))->handle($file, $manager);
        $this->fail('Expected unavailable storage to fail validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'file' => ['No fue posible acceder al archivo almacenado.'],
        ]);
    }

    $file->refresh();
    expect($file->status)->toBe(OwnRevenueImportFileStatus::Uploaded)
        ->and($file->analysis_token)->toBeNull()
        ->and($row->fresh()?->getRawOriginal())->toEqual($rowSnapshot)
        ->and($issue->fresh()?->getRawOriginal())->toEqual($issueSnapshot);
});

test('stored file hash mismatches keep their explicit validation error', function () {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => OwnRevenueBudget::factory(),
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'sha256' => hash('sha256', 'expected workbook'),
    ]);
    Storage::disk('local')->put($file->storage_path, 'different workbook');

    try {
        app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);
        $this->fail('Expected a mismatched stored file hash to fail validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'file' => ['La huella del archivo almacenado no coincide.'],
        ]);
    }

    expect($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Uploaded)
        ->and($file->fresh()->analysis_token)->toBeNull();
});

test('final analysis writes preserve a file confirmed after parsing started', function (string $outcome) {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create();
    $contents = 'stale analysis workbook';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $row = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
    ]);
    $issue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_import_row_id' => $row->id,
    ]);
    $decision = OwnRevenueImportDecision::factory()->create([
        'own_revenue_import_issue_id' => $issue->id,
        'own_revenue_import_row_id' => $row->id,
        'resolved_by' => $manager->id,
    ]);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(AbpreWorkbookParser::class);
    $parser->shouldReceive('parse')->once()->andReturnUsing(
        function () use ($file, $manager, $outcome): AbpreAnalysis {
            OwnRevenueImportFile::query()->whereKey($file)->update([
                'status' => OwnRevenueImportFileStatus::Confirmed,
                'confirmed_by' => $manager->id,
                'confirmed_at' => now(),
            ]);

            if ($outcome === 'failure') {
                throw new RuntimeException('Controlled parser failure');
            }

            return new AbpreAnalysis([], [], [], []);
        },
    );
    $action = new AnalyzeOwnRevenueImportFile($reader, $parser);
    $rowSnapshot = $row->getRawOriginal();
    $issueSnapshot = $issue->getRawOriginal();
    $decisionSnapshot = $decision->getRawOriginal();

    expect(fn () => $action->handle($file, $manager))
        ->toThrow(ValidationException::class);

    $file->refresh();
    expect($file->status)->toBe(OwnRevenueImportFileStatus::Confirmed)
        ->and($file->confirmed_by)->toBe($manager->id)
        ->and($file->confirmed_at)->not->toBeNull()
        ->and($row->fresh()?->getRawOriginal())->toEqual($rowSnapshot)
        ->and($issue->fresh()?->getRawOriginal())->toEqual($issueSnapshot)
        ->and($decision->fresh()?->getRawOriginal())->toEqual($decisionSnapshot);
})->with([
    'success persistence' => 'success',
    'failure persistence' => 'failure',
]);

test('stale analysis attempts cannot overwrite concurrent file state', function (string $mutation, string $outcome) {
    Storage::fake('local');
    $manager = analyzeImportUser();
    $budget = OwnRevenueBudget::factory()->create();
    $contents = 'overlapping analysis workbook';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Uploaded,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $row = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
    ]);
    $issue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_import_row_id' => $row->id,
        'code' => 'seed.issue',
    ]);
    $decision = OwnRevenueImportDecision::factory()->create([
        'own_revenue_import_issue_id' => $issue->id,
        'own_revenue_import_row_id' => $row->id,
        'resolved_by' => $manager->id,
    ]);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(AbpreWorkbookParser::class);
    $replacementToken = null;
    $parser->shouldReceive('parse')->once()->andReturnUsing(
        function () use ($mutation, $outcome, $file, $manager, &$replacementToken): AbpreAnalysis {
            $activeAttempt = $file->fresh();
            expect($activeAttempt->status)->toBe(OwnRevenueImportFileStatus::Analyzing)
                ->and(Str::isUuid($activeAttempt->analysis_token))->toBeTrue();

            if ($mutation === 'discard') {
                app(DiscardOwnRevenueImportFile::class)->handle($file->fresh(), $manager);
            } elseif ($mutation === 'format') {
                app(AssignOwnRevenueImportFormat::class)->handle(
                    $file->fresh(),
                    $manager,
                    OwnRevenueImportFormat::Fuel,
                );
            } else {
                $nextReader = Mockery::mock(XlsxWorkbookReader::class);
                $nextReader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
                $nextParser = Mockery::mock(AbpreWorkbookParser::class);
                $nextParser->shouldReceive('parse')->once()->andReturnUsing(
                    function () use ($file, $activeAttempt, &$replacementToken): never {
                        $secondAttempt = $file->fresh();
                        $replacementToken = $secondAttempt->analysis_token;

                        expect($secondAttempt->status)->toBe(OwnRevenueImportFileStatus::Analyzing)
                            ->and(Str::isUuid($replacementToken))->toBeTrue()
                            ->and($replacementToken)->not->toBe($activeAttempt->analysis_token);

                        throw ValidationException::withMessages([
                            'file' => 'Controlled pause after claiming the second attempt.',
                        ]);
                    },
                );

                expect(fn () => (new AnalyzeOwnRevenueImportFile($nextReader, $nextParser))->handle($file->fresh(), $manager))
                    ->toThrow(ValidationException::class);
            }

            if ($outcome === 'failure') {
                throw new RuntimeException('Controlled stale attempt failure');
            }

            return new AbpreAnalysis(
                [],
                [],
                [new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Info,
                    'first.attempt',
                    null,
                    'Resultado obsoleto del primer intento.',
                )],
                [],
            );
        },
    );

    expect(fn () => (new AnalyzeOwnRevenueImportFile($reader, $parser))->handle($file, $manager))
        ->toThrow(ValidationException::class);

    $file->refresh();

    if ($mutation === 'discard') {
        expect($file->status)->toBe(OwnRevenueImportFileStatus::Discarded)
            ->and($file->analysis_token)->toBeNull()
            ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
            ->and($row->fresh())->not->toBeNull()
            ->and($issue->fresh()?->code)->toBe('seed.issue')
            ->and($decision->fresh())->not->toBeNull();
    } elseif ($mutation === 'format') {
        expect($file->status)->toBe(OwnRevenueImportFileStatus::Analyzing)
            ->and(Str::isUuid($file->analysis_token))->toBeTrue()
            ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
            ->and($row->fresh())->not->toBeNull()
            ->and($issue->fresh()?->code)->toBe('seed.issue')
            ->and($decision->fresh())->not->toBeNull();
    } else {
        expect($file->status)->toBe(OwnRevenueImportFileStatus::Analyzing)
            ->and($file->analysis_token)->toBe($replacementToken)
            ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
            ->and($row->fresh())->not->toBeNull()
            ->and($issue->fresh()?->code)->toBe('seed.issue')
            ->and($decision->fresh())->not->toBeNull();
    }
})->with([
    'discard before successful completion' => ['discard', 'success'],
    'discard before failed completion' => ['discard', 'failure'],
    'format before successful completion' => ['format', 'success'],
    'format before failed completion' => ['format', 'failure'],
    'new attempt before successful completion' => ['overlap', 'success'],
    'new attempt before failed completion' => ['overlap', 'failure'],
]);
