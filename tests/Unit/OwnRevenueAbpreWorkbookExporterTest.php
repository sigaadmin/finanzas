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

    expect($sheet->getTitle())->toBe('ABRPRE-01')
        ->and($sheet->getCell('A1')->getValue())->toBe('PRESUPUESTO DE EGRESOS 2026')
        ->and($sheet->getCell('I7')->getValue())->toBe('02-001')
        ->and($sheet->getCell('L7')->getValue())->toBe('21101')
        ->and($sheet->getCell('Q7')->getValue())->toBe(123.45)
        ->and($sheet->getCell('Y7')->getValue())->toBe(123.45);
});
