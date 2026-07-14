<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Services\Finance\OwnRevenue\Imports\WorkSheetWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

/** @return array<string, string> */
function workSheetMonths(string $january = '0', string $february = '0'): array
{
    return [
        'G' => $january,
        'H' => $february,
        'I' => '0',
        'J' => '0',
        'K' => '0',
        'L' => '0',
        'M' => '0',
        'N' => '0',
        'O' => '0',
        'P' => '0',
        'Q' => '0',
        'R' => '0',
    ];
}

/** @param array<int, array<string, string>> $dataRows */
function workSheetParserFixture(array $dataRows, int $firstHeaderRow = 3): string
{
    return OwnRevenueXlsxFixtureFactory::create([
        'PORTADA' => [1 => ['A' => 'Documento de apoyo']],
        'HOJA FINAL' => [
            $firstHeaderRow => [
                'A' => 'Actividades / Unidad de Presupuestación',
                'B' => 'Insumos',
                'C' => 'Partida',
                'D' => 'Región',
                'E' => 'Nombre de la región',
                'F' => 'Presupuesto',
                'G' => 'Calendario',
            ],
            $firstHeaderRow + 1 => [
                'G' => 'Enero', 'H' => 'Febrero', 'I' => 'Marzo', 'J' => 'Abril',
                'K' => 'Mayo', 'L' => 'Junio', 'M' => 'Julio', 'N' => 'Agosto',
                'O' => 'Septiembre', 'P' => 'Octubre', 'Q' => 'Noviembre',
                'R' => 'Diciembre', 'S' => 'Anual',
            ],
            ...$dataRows,
        ],
    ]);
}

/** @return array{WorkSheetWorkbookParser, XlsxWorkbookReader} */
function workSheetParserServices(): array
{
    return [new WorkSheetWorkbookParser, new XlsxWorkbookReader];
}

test('work sheet parser locates shifted two-row headers and inherits the activity', function () {
    $fixture = workSheetParserFixture([
        12 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '10', ...workSheetMonths('10'), 'S' => '10'],
        13 => ['B' => 'Papelería', 'C' => '21102', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '20', ...workSheetMonths('20'), 'S' => '20'],
    ], 10);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse(
        $reader->read($fixture),
        ['A03-A01' => 7],
        ['21101' => 11, '21102' => 12],
    );

    expect($analysis->lines)->toHaveCount(2)
        ->and($analysis->lines[0]->activityCode)->toBe('A03-A01')
        ->and($analysis->lines[1]->activityCode)->toBe('A03-A01')
        ->and($analysis->sourceRows[0]['sheet_name'])->toBe('HOJA FINAL')
        ->and($analysis->sourceRows[0]['source_payload']['partida']['coordinate'])->toBe('C12');
});

test('work sheet parser groups normalized regions and adds exact cents', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '04-001', 'E' => 'CHETUMAL', 'F' => '10.01', ...workSheetMonths('10.01'), 'S' => '10.01'],
        6 => ['B' => 'Papelería', 'C' => '21101', 'D' => '05-001', 'E' => 'CANCÚN', 'F' => '0.99', ...workSheetMonths('0.99'), 'S' => '0.99'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 7], ['21101' => 11]);
    $codes = array_map(fn ($issue): string => $issue->code, $analysis->issues);

    expect($analysis->lines)->toHaveCount(1)
        ->and($analysis->lines[0]->regionCode)->toBe('02-001')
        ->and($analysis->lines[0]->regionName)->toBe('Felipe Carrillo Puerto')
        ->and($analysis->lines[0]->sourceRegions)->toBe([
            ['code' => '04-001', 'name' => 'CHETUMAL'],
            ['code' => '05-001', 'name' => 'CANCÚN'],
        ])
        ->and($analysis->lines[0]->months[1])->toBe('1100')
        ->and($analysis->lines[0]->annualAmountCents)->toBe('1100')
        ->and($analysis->lines[0]->sourceRows)->toBe([5, 6])
        ->and($codes)->toContain('region.normalized', 'work_sheet.duplicate_group');
});

test('work sheet parser warns when grouped rows use different item names', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        6 => ['B' => 'Consumibles de oficina', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '2', ...workSheetMonths('2'), 'S' => '2'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 7], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'work_sheet.item_name_mismatch');

    expect($issue)->not->toBeNull()
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Warning)
        ->and($issue->context['item_names'])->toBe(['Papelería', 'Consumibles de oficina']);
});

test('work sheet parser reports unknown activities and COG items as blocking issues', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A99-A01 - Actividad nueva', 'B' => 'Desconocido', 'C' => '99999', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 7], ['21101' => 11]);
    $issues = collect($analysis->issues);

    expect($issues->firstWhere('code', 'activity.missing')->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($issues->firstWhere('code', 'cog.missing_item')->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($analysis->lines)->toHaveCount(1);
});

test('work sheet parser ignores support totals signatures and blank rows', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        43 => ['N' => 'INGRESOS ESTIMADOS', 'S' => '1930075'],
        44 => ['L' => 'REDUCCIÓN 10%-15% REQUERIDA POR COORDINACIÓN DE FINANZAS', 'S' => '193007.5'],
        46 => ['N' => 'TOTAL PRESUPUESTADO', 'S' => '1795546'],
        47 => ['N' => 'INGRESOS ESTIMADOS - PRESUPUESTADO', 'S' => '134529'],
        50 => ['B' => 'ELABORÓ', 'H' => 'REVISÓ', 'P' => 'Vo.Bo.'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 7], ['21101' => 11]);

    expect($analysis->lines)->toHaveCount(1)
        ->and($analysis->sourceRows)->toHaveCount(1)
        ->and(array_column($analysis->sourceRows, 'row_number'))->toBe([5]);
});

test('work sheet parser recomputes annual amounts from months and warns about the declared annual', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '13.35', ...workSheetMonths('10.10', '3.25'), 'S' => '99.99'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 7], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'work_sheet.annual_mismatch');

    expect($analysis->lines[0]->annualAmountCents)->toBe('1335')
        ->and($issue)->not->toBeNull()
        ->and($issue->context)->toMatchArray(['source_cents' => '9999', 'calculated_cents' => '1335']);
});
