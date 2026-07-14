<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
