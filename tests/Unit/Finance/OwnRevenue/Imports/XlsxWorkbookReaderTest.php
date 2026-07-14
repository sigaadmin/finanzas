<?php

use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

test('reader preserves cached values formulas coordinates and sheet names', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'ABRPRE-01' => [
            6 => [
                'A' => ['value' => 'Clave Unidad Responsable', 'type' => 'shared'],
                'M' => 'Enero',
                'X' => 'Diciembre',
                'Y' => 'Anual',
            ],
            7 => [
                'A' => ['value' => '2112102003', 'type' => 'shared'],
                'Y' => ['value' => '1050', 'formula' => 'SUM(M7:X7)', 'type' => 'number'],
            ],
        ],
        'Formato Justificación Partidas' => [
            7 => ['A' => 'Unidad Responble', 'B' => 'Capítulo'],
        ],
    ]);

    $workbook = (new XlsxWorkbookReader)->read($fixture);
    $annual = $workbook->sheet('ABRPRE-01')->row(7)->cell('Y');

    expect($workbook->sheetNames())->toBe(['ABRPRE-01', 'Formato Justificación Partidas'])
        ->and($annual->coordinate)->toBe('Y7')
        ->and($annual->formula)->toBe('SUM(M7:X7)')
        ->and($annual->value)->toBe('1050')
        ->and($workbook->sheet('ABRPRE-01')->row(6)->cell('A')->value)->toBe('Clave Unidad Responsable');
});

test('reader preserves sparse cells and explicit empty cached values', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'FICHA' => [3 => ['A' => 'Primero', 'D' => ['value' => null, 'type' => 'number']]],
    ]);

    $row = (new XlsxWorkbookReader)->read($fixture)->sheet('FICHA')->row(3);

    expect($row->cell('A')->value)->toBe('Primero')
        ->and($row->cell('D')->value)->toBeNull()
        ->and($row->cells())->toHaveKeys(['A', 'D']);
});

test('reader rejects an invalid XLSX archive', function () {
    $path = tempnam(sys_get_temp_dir(), 'invalid-xlsx-');
    file_put_contents($path, 'not a zip');

    expect(fn () => (new XlsxWorkbookReader)->read($path))->toThrow(RuntimeException::class);
});
