<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetAnalysis;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetLineData;
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
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueImportViewData;
use App\Services\Finance\OwnRevenue\Imports\WorkSheetWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Support\Facades\Storage;

function workSheetReconciliationManager(): User
{
    $email = 'work-sheet-reconciliation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::create(['email' => $email, 'role' => UserRole::FinanceManager, 'is_active' => true]);

    return User::factory()->create(['email' => $email]);
}

/** @param list<WorkSheetLineData> $lines */
function analyzeWorkSheetForReconciliation(
    OwnRevenueBudget $budget,
    User $manager,
    array $lines,
): OwnRevenueImportFile {
    $contents = 'valid-work-sheet-'.fake()->uuid();
    $file = OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::WorkSheet,
        'status' => OwnRevenueImportFileStatus::ParserPending,
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->storage_path, $contents);

    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(WorkSheetWorkbookParser::class);
    $parser->shouldReceive('parse')->once()->andReturn(new WorkSheetAnalysis($lines, [], []));

    return (new AnalyzeOwnRevenueImportFile($reader, new AbpreWorkbookParser, $parser))->handle($file, $manager);
}

/** @param list<int> $sourceRows */
function reconciliationWorkSheetLine(string $activity, string $item, string $annualCents, array $sourceRows): WorkSheetLineData
{
    return new WorkSheetLineData(
        activityCode: $activity,
        activityName: 'Actividad '.$activity,
        itemName: 'Partida '.$item,
        specificItemCode: $item,
        regionCode: '02-001',
        regionName: 'Felipe Carrillo Puerto',
        sourceRegions: [['code' => '02-001', 'name' => 'Felipe Carrillo Puerto']],
        months: [1 => $annualCents, 2 => '0', 3 => '0', 4 => '0', 5 => '0', 6 => '0', 7 => '0', 8 => '0', 9 => '0', 10 => '0', 11 => '0', 12 => '0'],
        annualAmountCents: $annualCents,
        sourceRows: $sourceRows,
    );
}

function confirmedAbpreForReconciliation(OwnRevenueBudget $budget): OwnRevenueImportFile
{
    return OwnRevenueImportFile::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Confirmed,
        'confirmed_at' => now()->subMinute(),
    ]);
}

function reconciliationClassification(int $fiscalYear, string $code): ExpenseClassification
{
    return ExpenseClassification::query()->create([
        'fiscal_year' => $fiscalYear,
        'chapter_code' => '2000',
        'chapter_name' => 'Materiales',
        'concept_code' => '2100',
        'concept_name' => 'Administración',
        'generic_item_code' => substr($code, 0, 3).'00',
        'generic_item_name' => 'Insumos',
        'specific_item_code' => $code,
        'specific_item_name' => 'Partida '.$code,
        'expense_type_code' => '1',
        'expense_type_name' => 'Gasto corriente',
    ]);
}

test('work sheet reconciliation aggregates exact cents by item without changing confirmed ABPRE', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();
    $abpre = confirmedAbpreForReconciliation($budget);
    $classification = reconciliationClassification($budget->fiscal_year, '21101');
    $firstAbpreLine = OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => 1001,
    ]);
    $secondAbpreLine = OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => 234,
    ]);
    $snapshots = [$firstAbpreLine->getRawOriginal(), $secondAbpreLine->getRawOriginal()];

    $result = analyzeWorkSheetForReconciliation($budget, $manager, [
        reconciliationWorkSheetLine('A03-A01', '21101', '1000', [5]),
        reconciliationWorkSheetLine('A03-A02', '21101', '235', [6]),
    ]);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($result->issues()->where('code', 'work_sheet.abpre_mismatch')->count())->toBe(0)
        ->and($firstAbpreLine->fresh()?->getRawOriginal())->toEqual($snapshots[0])
        ->and($secondAbpreLine->fresh()?->getRawOriginal())->toEqual($snapshots[1]);
});

test('work sheet reconciliation reports positive negative and exclusive item differences with references', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();
    $abpre = confirmedAbpreForReconciliation($budget);
    $sharedClassification = reconciliationClassification($budget->fiscal_year, '21101');
    $abpreOnlyClassification = reconciliationClassification($budget->fiscal_year, '21201');
    $sharedLine = OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $sharedClassification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => 1000,
    ]);
    $abpreOnlyLine = OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $abpreOnlyClassification->id,
        'specific_item_code' => '21201',
        'annual_amount_cents' => 250,
    ]);

    $result = analyzeWorkSheetForReconciliation($budget, $manager, [
        reconciliationWorkSheetLine('A03-A01', '21101', '1001', [5, 7]),
        reconciliationWorkSheetLine('A03-A01', '21301', '99', [8]),
    ]);
    $issues = $result->issues()->where('code', 'work_sheet.abpre_mismatch')->get()->keyBy('field');

    expect($result->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($issues)->toHaveCount(3)
        ->and($issues['21101']->severity)->toBe(OwnRevenueImportIssueSeverity::Warning)
        ->and($issues['21101']->context)->toMatchArray([
            'specific_item_code' => '21101',
            'work_sheet_total_cents' => '1001',
            'abpre_total_cents' => '1000',
            'difference_cents' => '1',
            'abpre_import_file_id' => $abpre->id,
            'work_sheet_source_rows' => [5, 7],
            'abpre_line_ids' => [$sharedLine->id],
            'requires_decision' => true,
        ])
        ->and($issues['21201']->context['difference_cents'])->toBe('-250')
        ->and($issues['21201']->context['work_sheet_total_cents'])->toBe('0')
        ->and($issues['21201']->context['abpre_line_ids'])->toBe([$abpreOnlyLine->id])
        ->and($issues['21301']->context['difference_cents'])->toBe('99')
        ->and($issues['21301']->context['abpre_total_cents'])->toBe('0');

    $viewData = app(OwnRevenueImportViewData::class);
    $serializedIssue = collect($viewData->issues($result)['data'])
        ->first(fn (array $issue): bool => (string) ($issue['context']['specific_item_code'] ?? '') === '21101');
    expect($serializedIssue['context'])->toMatchArray([
        'work_sheet_total_cents' => '1001',
        'abpre_total_cents' => '1000',
        'difference_cents' => '1',
        'requires_decision' => true,
    ])->not->toHaveKeys(['abpre_import_file_id', 'abpre_line_ids'])
        ->and($serializedIssue)->not->toHaveKeys(['code', 'field'])
        ->and($viewData->decisionWarnings($result)['total'])->toBe(3);
});

test('work sheet analysis is blocked clearly when no usable confirmed ABPRE exists', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();

    $result = analyzeWorkSheetForReconciliation($budget, $manager, [
        reconciliationWorkSheetLine('A03-A01', '21101', '100', [5]),
    ]);
    $issue = $result->issues()->where('code', 'work_sheet.abpre_required')->sole();

    expect($result->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($issue->message)->toContain('Confirme el ABPRE')
        ->and($issue->context)->toMatchArray(['requires_reanalysis' => true]);
});

test('a confirmed ABPRE without lines is not usable for work sheet reconciliation', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();
    confirmedAbpreForReconciliation($budget);

    $result = analyzeWorkSheetForReconciliation($budget, $manager, [
        reconciliationWorkSheetLine('A03-A01', '21101', '100', [5]),
    ]);

    expect($result->status)->toBe(OwnRevenueImportFileStatus::NeedsCorrection)
        ->and($result->issues()->where('code', 'work_sheet.abpre_required')->count())->toBe(1);
});

test('reconciliation keeps cent precision above the javascript safe integer range', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();
    $abpre = confirmedAbpreForReconciliation($budget);
    $classification = reconciliationClassification($budget->fiscal_year, '21101');
    OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => '9007199254740993',
    ]);

    $result = analyzeWorkSheetForReconciliation($budget, $manager, [
        reconciliationWorkSheetLine('A03-A01', '21101', '9007199254740994', [5]),
    ]);
    $context = $result->issues()->where('code', 'work_sheet.abpre_mismatch')->sole()->context;

    expect($context['work_sheet_total_cents'])->toBe('9007199254740994')
        ->and($context['abpre_total_cents'])->toBe('9007199254740993')
        ->and($context['difference_cents'])->toBe('1');
});

test('successful reanalysis invalidates a prior mismatch decision with its old issue', function () {
    Storage::fake('local');
    $manager = workSheetReconciliationManager();
    $budget = OwnRevenueBudget::factory()->create();
    $abpre = confirmedAbpreForReconciliation($budget);
    $classification = reconciliationClassification($budget->fiscal_year, '21101');
    OwnRevenueAbpreLine::factory()->create([
        'own_revenue_budget_id' => $budget->id,
        'own_revenue_import_file_id' => $abpre->id,
        'expense_classification_id' => $classification->id,
        'specific_item_code' => '21101',
        'annual_amount_cents' => 100,
    ]);
    $lines = [reconciliationWorkSheetLine('A03-A01', '21101', '101', [5])];
    $file = analyzeWorkSheetForReconciliation($budget, $manager, $lines);
    $oldIssue = $file->issues()->where('code', 'work_sheet.abpre_mismatch')->sole();
    $decision = OwnRevenueImportDecision::factory()->create([
        'own_revenue_import_issue_id' => $oldIssue->id,
        'resolution' => 'accepted',
        'resolved_by' => $manager->id,
    ]);
    $reader = Mockery::mock(XlsxWorkbookReader::class);
    $reader->shouldReceive('read')->once()->andReturn(new XlsxWorkbook([]));
    $parser = Mockery::mock(WorkSheetWorkbookParser::class);
    $parser->shouldReceive('parse')->once()->andReturn(new WorkSheetAnalysis($lines, [], []));

    $result = (new AnalyzeOwnRevenueImportFile($reader, new AbpreWorkbookParser, $parser))
        ->handle($file->fresh(), $manager);

    expect($oldIssue->fresh())->toBeNull()
        ->and($decision->fresh())->toBeNull()
        ->and($result->issues()->where('code', 'work_sheet.abpre_mismatch')->sole()->id)->not->toBe($oldIssue->id)
        ->and($result->issues()->where('code', 'work_sheet.abpre_mismatch')->sole()->hasPendingRequiredDecision())->toBeTrue();
});
