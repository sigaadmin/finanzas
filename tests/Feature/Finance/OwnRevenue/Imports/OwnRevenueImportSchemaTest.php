<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('import files store nullable analysis attempt ownership', function () {
    expect(Schema::hasColumn('own_revenue_import_files', 'analysis_token'))->toBeTrue()
        ->and(OwnRevenueImportFile::factory()->create()->analysis_token)->toBeNull();
});

test('import schema keeps files rows issues and immutable ABPRE versions', function () {
    $budget = OwnRevenueBudget::factory()->create(['fiscal_year' => 2027]);
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();
    $file = OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'status' => OwnRevenueImportFileStatus::Ready,
        'sha256' => str_repeat('a', 64),
        'version_number' => 1,
    ]);
    $row = OwnRevenueImportRow::factory()->for($file, 'file')->create([
        'sheet_name' => 'ABRPRE-01',
        'row_number' => 7,
        'normalized_payload' => ['specific_item_code' => '21101'],
    ]);

    expect($budget->importSessions)->toHaveCount(1)
        ->and($file->format)->toBe(OwnRevenueImportFormat::Abpre)
        ->and($file->status)->toBe(OwnRevenueImportFileStatus::Ready)
        ->and($row->normalized_payload['specific_item_code'])->toBe('21101');
});

test('file versions are unique within a budget and format', function () {
    $budget = OwnRevenueBudget::factory()->create();
    $session = OwnRevenueImportSession::factory()->for($budget, 'budget')->create();

    OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 1,
    ]);

    expect(fn () => OwnRevenueImportFile::factory()->for($session, 'session')->create([
        'own_revenue_budget_id' => $budget->id,
        'format' => OwnRevenueImportFormat::Abpre,
        'version_number' => 1,
    ]))->toThrow(QueryException::class);
});

test('file factory keeps the session and budget in the same aggregate', function () {
    $file = OwnRevenueImportFile::factory()->create();

    expect($file->own_revenue_budget_id)->toBe($file->session->own_revenue_budget_id);
});

test('ABPRE factories keep their file and budget in the same aggregate', function () {
    $line = OwnRevenueAbpreLine::factory()->create();
    $justification = OwnRevenueAbpreJustification::factory()->create();

    expect($line->own_revenue_budget_id)->toBe($line->file->own_revenue_budget_id)
        ->and($justification->own_revenue_budget_id)->toBe($justification->file->own_revenue_budget_id);
});
