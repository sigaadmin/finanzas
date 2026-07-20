<?php

use App\Actions\Finance\OwnRevenue\Imports\AnalyzeOwnRevenueImportFile;
use App\Actions\Finance\OwnRevenue\Imports\StartOwnRevenueImportSession;
use App\Actions\Finance\OwnRevenue\Imports\UploadOwnRevenueImportFile;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\UserRole;
use App\Models\AuthorizedAccess;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueWorkbookFormatDetector;
use App\Services\Finance\OwnRevenue\Imports\SupportingWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array<string, array{pattern: string, format: OwnRevenueImportFormat, row_kind: string}> */
function realOwnRevenueWorkbookCases(): array
{
    return [
        'abpre' => [
            'pattern' => '*JUSTIFICACI*N DE PARTIDAS.xlsx',
            'format' => OwnRevenueImportFormat::Abpre,
            'row_kind' => 'abpre_line',
        ],
        'work_sheet' => [
            'pattern' => '*HOJA DE TRABAJO DE PRESUPUESTACI*N.xlsx',
            'format' => OwnRevenueImportFormat::WorkSheet,
            'row_kind' => 'work_sheet_normalized_line',
        ],
        'technical_sheet' => [
            'pattern' => '*FICHA T*CNICA.xlsx',
            'format' => OwnRevenueImportFormat::TechnicalSheet,
            'row_kind' => 'technical_sheet_normalized_line',
        ],
        'fuel' => [
            'pattern' => 'CRENFCP - COMBUSTIBLE.xlsx',
            'format' => OwnRevenueImportFormat::Fuel,
            'row_kind' => 'fuel_normalized_line',
        ],
        'travel_expenses' => [
            'pattern' => 'CRENFCP - VI*TICOS.xlsx',
            'format' => OwnRevenueImportFormat::TravelExpenses,
            'row_kind' => 'travel_expenses_normalized_line',
        ],
    ];
}

function realOwnRevenueWorkbookPath(string $directory, string $pattern): string
{
    $matches = glob($directory.DIRECTORY_SEPARATOR.$pattern);
    $matches = is_array($matches)
        ? array_values(array_filter($matches, fn (string $path): bool => ! str_starts_with(basename($path), '~$')))
        : $matches;

    expect($matches)
        ->not->toBeFalse()
        ->toHaveCount(1, "Se esperaba un solo archivo que coincidiera con {$pattern}.");

    return $matches[0];
}

test('the five institutional workbooks are detected and analyzed without changing the originals', function () {
    $directory = getenv('OWN_REVENUE_REAL_WORKBOOK_DIR');

    if (! is_string($directory) || $directory === '' || ! is_dir($directory)) {
        $this->markTestSkipped('Defina OWN_REVENUE_REAL_WORKBOOK_DIR para validar los archivos institucionales.');
    }

    Storage::fake('local');
    $email = 'real-workbook-validation-'.fake()->uuid().'@crenfcp.edu.mx';
    AuthorizedAccess::query()->create([
        'email' => $email,
        'role' => UserRole::FinanceManager,
        'is_active' => true,
    ]);
    $manager = User::factory()->create(['email' => $email]);
    $budget = OwnRevenueBudget::factory()->create([
        'fiscal_year' => 2026,
        'responsible_unit_code' => '2330',
    ]);
    $session = app(StartOwnRevenueImportSession::class)->handle($budget, $manager);
    $upload = app(UploadOwnRevenueImportFile::class);
    $analyze = app(AnalyzeOwnRevenueImportFile::class);
    $results = [];

    foreach (realOwnRevenueWorkbookCases() as $name => $case) {
        $path = realOwnRevenueWorkbookPath($directory, $case['pattern']);
        $originalHash = hash_file('sha256', $path);
        $file = $upload->handle(
            $session,
            $manager,
            new UploadedFile(
                $path,
                basename($path),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            ),
            false,
        );

        expect($file->format)->toBe($case['format']);

        $analyzed = $analyze->handle($file, $manager);
        $normalizedRows = $analyzed->rows()->where('row_kind', $case['row_kind'])->count();
        $diagnostic = json_encode([
            'format' => $name,
            'status' => $analyzed->status->value,
            'source_rows' => $analyzed->rows()->whereNot('sheet_name', 'like', '__normalized_%')->count(),
            'issues' => $analyzed->issues()->pluck('code')->countBy()->all(),
            'responsible_units' => $analyzed->issues()
                ->where('code', 'abpre.other_unit')
                ->get()
                ->pluck('context.responsible_unit_code')
                ->unique()
                ->values()
                ->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        expect($analyzed->status)->not->toBe(OwnRevenueImportFileStatus::Failed)
            ->and($analyzed->analyzed_at)->not->toBeNull()
            ->and(hash_file('sha256', $path))->toBe($originalHash);
        expect($normalizedRows)->toBeGreaterThan(0, $diagnostic);

        $results[$name] = [
            'status' => $analyzed->status->value,
            'rows' => $normalizedRows,
            'issues' => $analyzed->issues()->count(),
        ];
    }

    expect(array_keys($results))->toBe(array_keys(realOwnRevenueWorkbookCases()));

    $calendarPath = realOwnRevenueWorkbookPath($directory, '*CALENDARIO METAS POR REGI*N.xlsx');
    $calendarHash = hash_file('sha256', $calendarPath);
    $calendarDetection = app(OwnRevenueWorkbookFormatDetector::class)->detect(
        app(XlsxWorkbookReader::class)->read($calendarPath),
    );

    expect($calendarDetection->format)->toBeNull()
        ->and(hash_file('sha256', $calendarPath))->toBe($calendarHash);
});

test('the revised supporting workbooks preserve activity region and travel components', function () {
    $directory = getenv('OWN_REVENUE_REAL_SUPPORTING_WORKBOOK_DIR');

    if (! is_string($directory) || $directory === '' || ! is_dir($directory)) {
        $this->markTestSkipped('Defina OWN_REVENUE_REAL_SUPPORTING_WORKBOOK_DIR para validar los formatos complementarios revisados.');
    }

    $cases = [
        'technical_sheet' => ['pattern' => '*FICHA T*CNICA.xlsx', 'format' => OwnRevenueImportFormat::TechnicalSheet],
        'fuel' => ['pattern' => 'CRENFCP - COMBUSTIBLE.xlsx', 'format' => OwnRevenueImportFormat::Fuel],
        'travel_expenses' => ['pattern' => 'CRENFCP - VI*TICOS.xlsx', 'format' => OwnRevenueImportFormat::TravelExpenses],
    ];
    $activityMap = ['A01' => 1, 'A02' => 2, 'A03' => 3, 'A04' => 4];
    $reader = app(XlsxWorkbookReader::class);
    $detector = app(OwnRevenueWorkbookFormatDetector::class);
    $parser = app(SupportingWorkbookParser::class);

    foreach ($cases as $case) {
        $path = realOwnRevenueWorkbookPath($directory, $case['pattern']);
        $originalHash = hash_file('sha256', $path);
        $workbook = $reader->read($path);

        expect($detector->detect($workbook)->format)->toBe($case['format']);

        $analysis = $parser->parse(
            $workbook,
            $case['format'],
            [],
            2026,
            null,
            $activityMap,
        );
        $rows = collect($analysis->lines)->pluck('values');

        expect($rows)->not->toBeEmpty()
            ->and($rows->pluck('activityCode')->filter()->count())->toBe($rows->count())
            ->and($rows->pluck('activityCode')->unique()->diff(array_keys($activityMap)))->toBeEmpty()
            ->and(hash_file('sha256', $path))->toBe($originalHash);

        if ($case['format'] === OwnRevenueImportFormat::TechnicalSheet) {
            expect($rows->pluck('regionCode')->unique()->all())->toBe(['02-001'])
                ->and($rows->pluck('regionName')->unique()->all())->toBe(['Felipe Carrillo Puerto']);
        }

        if ($case['format'] === OwnRevenueImportFormat::TravelExpenses) {
            expect($rows->every(fn (array $row): bool => is_string($row['perDiemAmountCents'])))->toBeTrue()
                ->and($rows->every(fn (array $row): bool => is_string($row['lodgingAmountCents'])))->toBeTrue()
                ->and($rows->every(fn (array $row): bool => is_string($row['flightAmountCents'])))->toBeTrue()
                ->and($rows->pluck('flightAmountCents')->filter(fn (string $amount): bool => $amount !== '0')->count())->toBeGreaterThan(0);
        }
    }
});
