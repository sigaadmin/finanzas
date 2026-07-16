<?php

use App\Services\Finance\OwnRevenue\Exports\AbpreWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it creates an ABPRE workbook from authorized reconciliation lines', function () {
    $contents = app(AbpreWorkbookExporter::class)->export([
        'budget' => ['fiscal_year' => 2026, 'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto'],
        'reconciliation' => ['groups' => [[
            'specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '12345',
        ]]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'abpre-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('ABPRE')
        ->and($sheet->getCell('A1')->getValue())->toBe('Presupuesto de Ingresos Propios 2026')
        ->and($sheet->getCell('A4')->getValue())->toBe('21101')
        ->and($sheet->getCell('B4')->getValue())->toBe(5)
        ->and($sheet->getCell('C4')->getValue())->toBe(123.45);
});
