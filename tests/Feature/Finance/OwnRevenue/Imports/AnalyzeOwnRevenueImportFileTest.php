<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Data\Finance\OwnRevenue\Imports\AbpreAnalysis;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
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
use Illuminate\Validation\ValidationException;

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
        ->and($file->rows()->count())->toBeGreaterThan(0)
        ->and($file->issues()->where('severity', 'error')->count())->toBeGreaterThan(0)
        ->and(OwnRevenueAbpreLine::query()->count())->toBe(0);

    $rowCount = $file->rows()->count();
    app(AnalyzeOwnRevenueImportFile::class)->handle($file->fresh(), $manager);

    expect($file->rows()->count())->toBe($rowCount);
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
