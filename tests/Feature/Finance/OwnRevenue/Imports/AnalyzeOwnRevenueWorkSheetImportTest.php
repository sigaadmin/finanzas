<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetAnalysis;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportDecision;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportIssue;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\WorkSheetWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/WorkSheetXlsxFixtures.php';

function workSheetAnalysisManager(): User
{
    $email = 'work-sheet-analysis-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => UserRole::FinanceManager, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

function workSheetAnalysisCog(int $year, string $code = '21101'): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $year,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales',
        'concept_code' => '2100',
        'concept_name' => 'Administración',
        'generic_item_code' => '21100',
        'generic_item_name' => 'Oficina',
        'specific_item_code' => $code,
        'specific_item_name' => 'Papelería',
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}

/** @param array<int, array<string, string|array{value?: string|null, formula?: string, type?: string}>> $rows */
function storedWorkSheetForAnalysis(OwnRevenueBudget $budget, User $manager, array $rows): OwnRevenueImportFile
{
    $fixture = workSheetParserFixture($rows);
    $contents = file_get_contents($fixture);
    expect($contents)->not->toBeFalse();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'uploaded_by' => $manager->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'detected_format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::ParserPending,
        'sha256' => hash('sha256', $contents),
        'original_name' => 'Hoja de trabajo.xlsx',
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);

    return $file;
}

test('work sheet analysis persists source and normalized staging with warnings without confirming lines', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $fiscalYear = ((int) OwnRevenueBudget::query()->max('fiscal_year')) + 1;
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => $fiscalYear]);
    $budget->activities()->create(['code' => 'A03-A01', 'name' => 'Investigación']);
    workSheetAnalysisCog($fiscalYear);
    $file = storedWorkSheetForAnalysis($budget, $manager, [
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '04-001', 'E' => 'CHETUMAL', 'F' => '10.01', ...workSheetMonths('10.01'), 'S' => '10.01'],
    ]);

    $result = app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($result->analysis_token)->toBeNull()
        ->and($result->analyzed_at)->not->toBeNull()
        ->and($result->rows()->where('row_kind', 'work_sheet_line')->count())->toBe(1)
        ->and($result->rows()->where('row_kind', 'work_sheet_normalized_line')->count())->toBe(1)
        ->and($result->issues()->where('code', 'region.normalized')->sole()->severity)->toBe(OwnRevenueImportIssueSeverity::Warning)
        ->and(OwnRevenueWorkSheetLine::query()->count())->toBe(0);

    $normalized = $result->rows()->where('row_kind', 'work_sheet_normalized_line')->sole();
    expect($normalized->normalized_payload)->toMatchArray([
        'activityCode' => 'A03-A01',
        'specificItemCode' => '21101',
        'regionCode' => '02-001',
        'annualAmountCents' => '1001',
    ]);
});

test('work sheet analysis resolves only activities from its budget and COG from its fiscal year', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $fiscalYear = ((int) OwnRevenueBudget::query()->max('fiscal_year')) + 1;
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => $fiscalYear]);
    $otherBudget = OwnRevenueBudget::factory()->create(['fiscal_year' => $fiscalYear + 1]);
    $otherBudget->activities()->create(['code' => 'A03-A99', 'name' => 'Ajena']);
    workSheetAnalysisCog($fiscalYear - 1, '21199');
    $file = storedWorkSheetForAnalysis($budget, $manager, [
        5 => ['A' => 'A03-A99 - Ajena', 'B' => 'Insumo', 'C' => '21199', 'D' => '02-001', 'E' => 'Felipe Carrillo Puerto', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
    ]);

    $result = app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($result->issues()->where('code', 'activity.missing')->count())->toBe(1)
        ->and($result->issues()->where('code', 'cog.missing_item')->count())->toBe(1)
        ->and($result->issues()->whereIn('code', ['activity.missing', 'cog.missing_item'])
            ->whereNotNull('own_revenue_import_row_id')->count())->toBe(2);
});

test('reanalyzing a work sheet atomically replaces prior staging and issues', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $fiscalYear = ((int) OwnRevenueBudget::query()->max('fiscal_year')) + 1;
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => $fiscalYear]);
    $budget->activities()->create(['code' => 'A03-A01', 'name' => 'Investigación']);
    workSheetAnalysisCog($fiscalYear);
    $file = storedWorkSheetForAnalysis($budget, $manager, [
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '04-001', 'E' => 'CHETUMAL', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
    ]);

    app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);
    $oldRowIds = $file->rows()->pluck('id')->all();
    $oldIssueIds = $file->issues()->pluck('id')->all();
    app(AnalyzeOwnRevenueImportFile::class)->handle($file->fresh(), $manager);

    expect($file->rows()->count())->toBe(2)
        ->and($file->issues()->count())->toBe(1)
        ->and($file->rows()->whereKey($oldRowIds)->count())->toBe(0)
        ->and($file->issues()->whereKey($oldIssueIds)->count())->toBe(0);
});

test('a failed work sheet reanalysis preserves valid staging decisions and one operational error', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $fiscalYear = ((int) OwnRevenueBudget::query()->max('fiscal_year')) + 1;
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => $fiscalYear]);
    $activity = $budget->activities()->create(['code' => 'A03-A01', 'name' => 'Investigación']);
    workSheetAnalysisCog($fiscalYear);
    $contents = 'invalid workbook';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::Ready,
        'sha256' => hash('sha256', $contents),
        'budget_updated_at_at_analysis' => $budget->updated_at,
        'analyzed_at' => now()->subMinute(),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $sourceRow = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'sheet_name' => 'HOJA FINAL',
        'row_number' => 5,
        'row_kind' => 'work_sheet_line',
    ]);
    $normalizedRow = OwnRevenueImportRow::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'sheet_name' => '__normalized_work_sheet__',
        'row_number' => 1,
        'row_kind' => 'work_sheet_normalized_line',
    ]);
    $domainIssue = OwnRevenueImportIssue::factory()->create([
        'own_revenue_import_file_id' => $file->id,
        'own_revenue_import_row_id' => $sourceRow->id,
        'severity' => OwnRevenueImportIssueSeverity::Warning,
        'code' => 'region.normalized',
    ]);
    $decision = OwnRevenueImportDecision::factory()->create([
        'own_revenue_import_issue_id' => $domainIssue->id,
        'own_revenue_import_row_id' => $sourceRow->id,
        'resolved_by' => $manager->id,
    ]);
    $validAnalyzedAt = $file->analyzed_at;
    $validBudgetSnapshot = $file->budget_updated_at_at_analysis;

    $result = app(AnalyzeOwnRevenueImportFile::class)->handle($file, $manager);
    $operationalIssueId = $result->issues()->where('code', 'analysis.failed')->sole()->id;
    $secondResult = app(AnalyzeOwnRevenueImportFile::class)->handle($result, $manager);

    expect($secondResult->status)->toBe(OwnRevenueImportFileStatus::Failed)
        ->and($secondResult->analysis_token)->toBeNull()
        ->and($secondResult->analyzed_at?->equalTo($validAnalyzedAt))->toBeTrue()
        ->and($secondResult->budget_updated_at_at_analysis?->equalTo($validBudgetSnapshot))->toBeTrue()
        ->and($secondResult->rows()->count())->toBe(2)
        ->and($secondResult->issues()->count())->toBe(2)
        ->and($sourceRow->fresh())->not->toBeNull()
        ->and($normalizedRow->fresh())->not->toBeNull()
        ->and($domainIssue->fresh())->not->toBeNull()
        ->and($decision->fresh())->not->toBeNull()
        ->and($secondResult->issues()->where('code', 'analysis.failed')->count())->toBe(1)
        ->and($secondResult->issues()->where('code', 'analysis.failed')->sole()->id)->toBe($operationalIssueId);

    $fixture = workSheetParserFixture([
        5 => ['A' => $activity->code.' - '.$activity->name, 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'Felipe Carrillo Puerto', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
    ]);
    $validContents = file_get_contents($fixture);
    expect($validContents)->not->toBeFalse();
    Storage::disk('local')->put($file->storage_path, $validContents);
    $secondResult->update(['sha256' => hash('sha256', $validContents)]);

    $successfulResult = app(AnalyzeOwnRevenueImportFile::class)->handle($secondResult->fresh(), $manager);

    expect($successfulResult->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($sourceRow->fresh())->toBeNull()
        ->and($normalizedRow->fresh())->toBeNull()
        ->and($domainIssue->fresh())->toBeNull()
        ->and($decision->fresh())->toBeNull()
        ->and($successfulResult->issues()->where('code', 'analysis.failed')->count())->toBe(0)
        ->and($successfulResult->rows()->where('row_kind', 'work_sheet_normalized_line')->count())->toBe(1);
});

test('a stale work sheet attempt cannot replace newer staging or file ownership', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $budget = OwnRevenueBudget::factory()->create();
    $contents = 'overlapping workbook';
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::ParserPending,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);
    $row = OwnRevenueImportRow::factory()->create(['own_revenue_import_file_id' => $file->id]);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $workSheetParser = Mockery::mock(WorkSheetWorkbookParser::class);
    $replacementToken = (string) Str::uuid();
    $workSheetParser->shouldReceive('parse')->once()->andReturnUsing(
        function () use ($file, $replacementToken): WorkSheetAnalysis {
            OwnRevenueImportFile::query()->whereKey($file)->update([
                'status' => OwnRevenueImportFileStatus::Analyzing,
                'analysis_token' => $replacementToken,
            ]);

            return new WorkSheetAnalysis([], [], []);
        },
    );
    $action = new AnalyzeOwnRevenueImportFile($reader, new AbpreWorkbookParser, $workSheetParser);

    expect(fn () => $action->handle($file, $manager))->toThrow(ValidationException::class);
    expect($file->fresh()->analysis_token)->toBe($replacementToken)
        ->and($file->fresh()->status)->toBe(OwnRevenueImportFileStatus::Analyzing)
        ->and($row->fresh())->not->toBeNull();
});

test('work sheet analysis endpoint enforces authorization aggregate boundaries and supported formats', function () {
    Storage::fake('local');
    $manager = workSheetAnalysisManager();
    $budget = OwnRevenueBudget::factory()->create();
    $otherBudget = OwnRevenueBudget::factory()->create();
    $workSheet = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::ParserPending,
    ]);
    $unsupported = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Fuel,
        'status' => OwnRevenueImportFileStatus::ParserPending,
    ]);
    $auditorEmail = 'work-sheet-auditor-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $auditorEmail, 'role' => UserRole::FinanceAuditor, 'is_active' => true]);
    $auditor = User::factory()->create(['email' => $auditorEmail]);

    $route = fn (OwnRevenueBudget $routeBudget, OwnRevenueImportFile $file): string => route(
        'finance.own-revenue.budgets.imports.files.analyze',
        ['budget' => $routeBudget, 'importFile' => $file],
    );

    $this->post($route($budget, $workSheet))->assertRedirect(route('login'));
    $this->actingAs($auditor)->post($route($budget, $workSheet))->assertForbidden();
    $this->actingAs($manager)->post($route($otherBudget, $workSheet))->assertNotFound();
    $this->actingAs($manager)->post($route($budget, $unsupported))->assertSessionHasErrors([
        'file' => 'Este analizador sólo admite los formatos ABPRE y Hoja de trabajo.',
    ]);
});
