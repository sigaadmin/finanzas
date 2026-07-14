<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreJustification;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueAbpreLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportRow;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportSession;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetLine;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueWorkSheetMonth;
use App\Models\Finance\OwnRevenue\OwnRevenueActivity;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('import files store nullable analysis attempt ownership', function () {
    expect(Schema::hasColumn('own_revenue_import_files', 'analysis_token'))->toBeTrue()
        ->and(Schema::hasColumn('own_revenue_import_files', 'analysis_revision'))->toBeTrue()
        ->and(OwnRevenueImportFile::factory()->create()->analysis_token)->toBeNull()
        ->and(OwnRevenueImportFile::factory()->create()->analysis_revision)->toBeNull();
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

test('work sheet analysis stores its ABPRE snapshot and preview rows have a covering index', function () {
    expect(Schema::hasColumn('own_revenue_import_files', 'abpre_import_file_id_at_analysis'))->toBeTrue();

    $indexes = collect(Schema::getIndexes('own_revenue_import_rows'));

    expect($indexes->contains(fn (array $index): bool => $index['columns'] === [
        'own_revenue_import_file_id',
        'row_kind',
        'row_number',
    ]))->toBeTrue();
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

test('work sheet schema stores confirmed activity item calendarization with traceable origins', function () {
    $line = OwnRevenueWorkSheetLine::factory()->create([
        'activity_code' => 'A03-A01',
        'activity_name' => 'Servicios escolares',
        'item_name' => 'Materiales y útiles de oficina',
        'specific_item_code' => '21101',
        'region_code' => '02-001',
        'region_name' => 'Felipe Carrillo Puerto',
        'annual_amount_cents' => 12_345,
    ]);
    $months = OwnRevenueWorkSheetMonth::factory()
        ->count(12)
        ->for($line, 'line')
        ->sequence(fn (Sequence $sequence): array => [
            'month' => $sequence->index + 1,
            'amount_cents' => match ($sequence->index) {
                0 => 1_234,
                11 => 1_111,
                default => 1_000,
            },
        ])
        ->create();
    $month = $months->firstWhere('month', 1);
    $lineOrigin = $line->origins()->create([
        'own_revenue_import_row_id' => $line->file->rows()->create([
            'sheet_name' => 'HOJA FINAL',
            'row_number' => 5,
            'row_kind' => 'work_sheet_line',
            'row_hash' => str_repeat('b', 64),
            'source_payload' => ['region_code' => '01-001'],
            'normalized_payload' => ['region_code' => '02-001'],
        ])->id,
        'field_name' => null,
    ]);
    $monthOrigin = $month->origins()->create([
        'own_revenue_import_row_id' => $lineOrigin->own_revenue_import_row_id,
        'field_name' => 'january_amount_cents',
    ]);

    expect($line->own_revenue_budget_id)->toBe($line->file->own_revenue_budget_id)
        ->and($line->file->format)->toBe(OwnRevenueImportFormat::WorkSheet)
        ->and($line->own_revenue_budget_id)->toBe($line->activity->own_revenue_budget_id)
        ->and($line->activity)->toBeInstanceOf(OwnRevenueActivity::class)
        ->and($line->activity_code)->toBe($line->activity->code)
        ->and($line->activity_name)->toBe($line->activity->name)
        ->and($line->budget->fiscal_year)->toBe($line->expenseClassification->fiscal_year)
        ->and($line->expenseClassification->specific_item_code)->toBe('21101')
        ->and($line->activity_code)->toBe('A03-A01')
        ->and($line->item_name)->toBe('Materiales y útiles de oficina')
        ->and($line->region_code)->toBe('02-001')
        ->and($line->annual_amount_cents)->toBe(12_345)
        ->and($months)->toHaveCount(12)
        ->and($months->pluck('month')->all())->toBe(range(1, 12))
        ->and($months->sum('amount_cents'))->toBe($line->annual_amount_cents)
        ->and($month->amount_cents)->toBe(1_234)
        ->and($lineOrigin->originable->is($line))->toBeTrue()
        ->and($monthOrigin->originable->is($month))->toBeTrue();
});

test('work sheet lines are unique by file activity item and region', function () {
    $line = OwnRevenueWorkSheetLine::factory()->create();

    expect(fn () => OwnRevenueWorkSheetLine::factory()->create([
        'own_revenue_budget_id' => $line->own_revenue_budget_id,
        'own_revenue_import_file_id' => $line->own_revenue_import_file_id,
        'own_revenue_activity_id' => $line->own_revenue_activity_id,
        'expense_classification_id' => $line->expense_classification_id,
        'region_code' => $line->region_code,
    ]))->toThrow(QueryException::class);
});

test('work sheet months are unique within a line', function () {
    $month = OwnRevenueWorkSheetMonth::factory()->create(['month' => 1]);

    expect(fn () => OwnRevenueWorkSheetMonth::factory()->for($month->line, 'line')->create([
        'month' => 1,
    ]))->toThrow(QueryException::class);
});

test('work sheet months reject values outside the calendar year', function (int $month) {
    expect(fn () => OwnRevenueWorkSheetMonth::factory()->create([
        'month' => $month,
    ]))->toThrow(QueryException::class);
})->with([0, 13]);
