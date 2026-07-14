<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Services\Finance\OwnRevenue\Imports\WorkSheetWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/WorkSheetXlsxFixtures.php';

/** @return array{WorkSheetWorkbookParser, XlsxWorkbookReader} */
function workSheetParserServices(): array
{
    return [new WorkSheetWorkbookParser, new XlsxWorkbookReader];
}

test('work sheet parser locates shifted two-row headers and inherits the activity', function () {
    $fixture = workSheetParserFixture([
        12 => [
            'A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101',
            'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '10.10',
            ...workSheetMonths(),
            'G' => ['value' => '10.10', 'formula' => 'SUM(A1:A2)', 'type' => 'numeric'],
            'S' => '10.10',
        ],
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
        ->and($analysis->sourceRows[0]['source_payload']['partida'])->toMatchArray([
            'coordinate' => 'C12',
            'value' => '21101',
            'formula' => null,
        ])
        ->and($analysis->sourceRows[0]['source_payload']['enero'])->toBe([
            'coordinate' => 'G12',
            'value' => '10.10',
            'formula' => 'SUM(A1:A2)',
        ])
        ->and($analysis->sourceRows[0]['normalized_payload'])->toMatchArray([
            'actividad' => 'A03-A01 - Investigación',
            'partida' => '21101',
            'region' => '02-001',
            'nombre region' => 'Felipe Carrillo Puerto',
            'months' => [
                1 => '1010', 2 => '0', 3 => '0', 4 => '0', 5 => '0', 6 => '0',
                7 => '0', 8 => '0', 9 => '0', 10 => '0', 11 => '0', 12 => '0',
            ],
            'annual_amount_cents' => '1010',
        ]);
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

test('work sheet parser handles a sanitized reproduction of the real sheet and ignores its support rows', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Actividades para el fomento de la Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        7 => ['A' => 'A03-A02 - Actividades para el profesorado y la docencia', 'B' => 'Combustible', 'C' => '26101', 'D' => '04-001', 'E' => 'CHETUMAL', 'F' => '2', ...workSheetMonths('2'), 'S' => '2'],
        8 => ['B' => 'Combustible', 'C' => '26101', 'D' => '05-001', 'E' => 'CANCÚN', 'F' => '3', ...workSheetMonths('3'), 'S' => '3'],
        43 => ['N' => 'INGRESOS ESTIMADOS', 'S' => '1930075'],
        44 => ['L' => 'REDUCCIÓN 10%-15% REQUERIDA POR COORDINACIÓN DE FINANZAS', 'S' => '193007.5'],
        46 => ['N' => 'TOTAL PRESUPUESTADO', 'S' => '1795546'],
        47 => ['N' => 'INGRESOS ESTIMADOS - PRESUPUESTADO', 'S' => '134529'],
        50 => ['B' => 'ELABORÓ', 'H' => 'REVISÓ', 'P' => 'Vo.Bo.'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse(
        $reader->read($fixture),
        ['A03-A01' => 7, 'A03-A02' => 8],
        ['21101' => 11, '26101' => 12],
    );

    expect($analysis->lines)->toHaveCount(2)
        ->and($analysis->sourceRows)->toHaveCount(3)
        ->and(array_column($analysis->sourceRows, 'row_number'))->toBe([5, 7, 8])
        ->and($analysis->lines[1]->annualAmountCents)->toBe('500');
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

test('work sheet parser reports malformed item codes and safely advances activity context', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        6 => ['A' => 'A03-A02 - Docencia', 'B' => 'Código mal capturado', 'C' => '2110A', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        7 => ['B' => 'Consumibles', 'C' => '21102', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '2', ...workSheetMonths('2'), 'S' => '2'],
        9 => ['B' => 'Código corto', 'C' => '9999', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        43 => ['N' => 'INGRESOS ESTIMADOS', 'S' => '1930075'],
        50 => ['B' => 'ELABORÓ', 'H' => 'REVISÓ', 'P' => 'Vo.Bo.'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse(
        $reader->read($fixture),
        ['A03-A01' => 1, 'A03-A02' => 2],
        ['21101' => 11, '21102' => 12],
    );
    $invalidItems = collect($analysis->issues)->where('code', 'work_sheet.invalid_item_code')->values();

    expect($invalidItems)->toHaveCount(2)
        ->and($invalidItems[0]->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($invalidItems[0]->rowNumber)->toBe(6)
        ->and($invalidItems[1]->context['specific_item_code'])->toBe('9999')
        ->and($analysis->lines)->toHaveCount(2)
        ->and($analysis->lines[1]->activityCode)->toBe('A03-A02')
        ->and(array_column($analysis->sourceRows, 'row_number'))->toBe([5, 6, 7, 9]);
});

test('work sheet parser blocks monthly formulas without a cached value instead of treating them as zero', function () {
    $fixture = workSheetParserFixture([
        5 => [
            'A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101',
            'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '10',
            ...workSheetMonths(),
            'G' => ['value' => null, 'formula' => 'SUM(A1:A2)', 'type' => 'number'],
            'S' => '0',
        ],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'amount.invalid');

    expect($issue)->not->toBeNull()
        ->and($issue->field)->toBe('enero')
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($analysis->lines)->toBeEmpty();
});

test('work sheet parser keeps a line when the comparative annual has no cached value', function () {
    $fixture = workSheetParserFixture([
        5 => [
            'A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101',
            'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '10',
            ...workSheetMonths('10'),
            'S' => ['value' => null, 'formula' => 'SUM(G5:R5)', 'type' => 'number'],
        ],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'work_sheet.annual_unavailable');

    expect($analysis->lines)->toHaveCount(1)
        ->and($analysis->lines[0]->annualAmountCents)->toBe('1000')
        ->and($issue)->not->toBeNull()
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Warning)
        ->and($issue->context['formula'])->toBe('SUM(G5:R5)');
});

test('work sheet parser keeps monthly data when the comparative annual is empty or invalid', function (string $annual) {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '10', ...workSheetMonths('10'), 'S' => $annual],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);

    expect($analysis->lines)->toHaveCount(1)
        ->and($analysis->lines[0]->annualAmountCents)->toBe('1000')
        ->and(collect($analysis->issues)->where('code', 'work_sheet.annual_unavailable'))->toHaveCount(1)
        ->and(collect($analysis->issues)->where('severity', OwnRevenueImportIssueSeverity::Error))->toBeEmpty();
})->with([
    'vacío' => '',
    'inválido' => 'importe no válido',
]);

test('work sheet parser prioritizes the semantic HOJA FINAL over an earlier compatible sheet', function () {
    $headers = [
        3 => ['A' => 'Actividades / Unidad de Presupuestación', 'B' => 'Insumos', 'C' => 'Partida', 'D' => 'Región', 'E' => 'Nombre de la región', 'F' => 'Presupuesto', 'G' => 'Calendario'],
        4 => ['G' => 'Enero', 'H' => 'Febrero', 'I' => 'Marzo', 'J' => 'Abril', 'K' => 'Mayo', 'L' => 'Junio', 'M' => 'Julio', 'N' => 'Agosto', 'O' => 'Septiembre', 'P' => 'Octubre', 'Q' => 'Noviembre', 'R' => 'Diciembre', 'S' => 'Anual'],
    ];
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'SEÑUELO' => $headers + [5 => ['A' => 'A03-A01 - Señuelo', 'B' => 'Incorrecto', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '99', ...workSheetMonths('99'), 'S' => '99']],
        '  HÓJA   FÍNAL  ' => $headers + [5 => ['A' => 'A03-A01 - Correcta', 'B' => 'Correcto', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1']],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);

    expect($analysis->sourceRows[0]['sheet_name'])->toBe('  HÓJA   FÍNAL  ')
        ->and($analysis->lines[0]->activityName)->toBe('Correcta')
        ->and($analysis->lines[0]->annualAmountCents)->toBe('100');
});

test('work sheet parser blocks annual overflow across months', function () {
    $maximumPesos = '184467440737095516.15';
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => $maximumPesos, ...workSheetMonths($maximumPesos, '0.01'), 'S' => $maximumPesos],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'amount.overflow');

    expect($issue)->not->toBeNull()
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($issue->field)->toBe('annual')
        ->and($analysis->lines)->toBeEmpty();
});

test('work sheet parser blocks overflow while grouping duplicate months', function () {
    $maximumPesos = '184467440737095516.15';
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => $maximumPesos, ...workSheetMonths($maximumPesos), 'S' => $maximumPesos],
        6 => ['B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '0.01', ...workSheetMonths('0.01'), 'S' => '0.01'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);
    $issue = collect($analysis->issues)->firstWhere('code', 'amount.overflow');

    expect($issue)->not->toBeNull()
        ->and($issue->severity)->toBe(OwnRevenueImportIssueSeverity::Error)
        ->and($issue->field)->toBe('enero')
        ->and($analysis->lines)->toBeEmpty();
});

test('work sheet parser adopts the first non-empty grouped item name', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => '', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        6 => ['B' => 'Consumibles de oficina', 'C' => '21101', 'D' => '02-001', 'E' => 'FELIPE CARRILLO PUERTO', 'F' => '2', ...workSheetMonths('2'), 'S' => '2'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);

    expect($analysis->lines[0]->itemName)->toBe('Consumibles de oficina');
});

test('work sheet parser deduplicates semantic region warnings and accepts equivalent institutional names', function () {
    $fixture = workSheetParserFixture([
        5 => ['A' => 'A03-A01 - Investigación', 'B' => 'Papelería', 'C' => '21101', 'D' => '02-001', 'E' => '  FÉLIPE   CARRILLO  PÚERTO ', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        6 => ['B' => 'Papelería', 'C' => '21101', 'D' => '04-001', 'E' => 'CHETUMAL', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
        7 => ['B' => 'Papelería', 'C' => '21101', 'D' => '04-001', 'E' => '  Chétumal ', 'F' => '1', ...workSheetMonths('1'), 'S' => '1'],
    ]);
    [$parser, $reader] = workSheetParserServices();

    $analysis = $parser->parse($reader->read($fixture), ['A03-A01' => 1], ['21101' => 11]);
    $regionIssues = collect($analysis->issues)->where('code', 'region.normalized')->values();

    expect($regionIssues)->toHaveCount(1)
        ->and($regionIssues[0]->context['source_region'])->toBe('04-001')
        ->and($analysis->lines[0]->regionCode)->toBe('02-001')
        ->and($analysis->lines[0]->sourceRegions)->toHaveCount(3);
});
