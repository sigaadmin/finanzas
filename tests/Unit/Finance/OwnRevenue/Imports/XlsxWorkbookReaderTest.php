<?php

use App\Services\Finance\OwnRevenue\Imports\InvalidXlsxWorkbookException;
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

test('reader reconstructs shared formulas for master and follower cells', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'HOJA FINAL' => [
            5 => ['G' => [
                'value' => '10',
                'formula' => 'A5+B5',
                'formula_attributes' => ['t' => 'shared', 'si' => '4', 'ref' => 'G5:G6'],
                'type' => 'number',
            ]],
            6 => ['G' => [
                'value' => '20',
                'formula' => '',
                'formula_attributes' => ['t' => 'shared', 'si' => '4'],
                'type' => 'number',
            ]],
        ],
    ]);

    $sheet = (new XlsxWorkbookReader)->read($fixture)->sheet('HOJA FINAL');

    expect($sheet->row(5)->cell('G')->formula)->toBe('A5+B5')
        ->and($sheet->row(6)->cell('G')->formula)->toBe('A6+B6');
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

    expect(fn () => (new XlsxWorkbookReader)->read($path))->toThrow(InvalidXlsxWorkbookException::class);
});

test('reader rejects highly compressible XLSX entries before materializing them', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'FICHA' => [3 => ['A' => 'FECHAS DE LA COMISION']],
    ]);
    $zip = new ZipArchive;

    expect($zip->open($fixture))->toBeTrue();
    $zip->addFromString('xl/media/padding.bin', str_repeat('A', 1024 * 1024));
    $zip->close();

    expect(filesize($fixture))->toBeLessThan(20 * 1024)
        ->and(fn () => (new XlsxWorkbookReader)->read($fixture))
        ->toThrow(InvalidXlsxWorkbookException::class, 'proporción de compresión');
});

test('reader limits parsed sheets even when names repeat', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'FICHA' => [3 => ['A' => 'FECHAS DE LA COMISION']],
    ]);
    $sheets = '';

    for ($number = 1; $number <= 257; $number++) {
        $sheets .= '<sheet name="FICHA" sheetId="'.$number.'" r:id="rId1"/>';
    }

    $workbook = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets>'.$sheets.'</sheets></workbook>';
    $zip = new ZipArchive;

    expect($zip->open($fixture))->toBeTrue();
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->close();

    expect(fn () => (new XlsxWorkbookReader)->read($fixture))
        ->toThrow(InvalidXlsxWorkbookException::class, 'límite de hojas');
});
