<?php

use App\Services\Finance\OwnRevenue\Exports\AbpreWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports the institutional abpre columns with twelve months and annual amount', function () {
    $contents = app(AbpreWorkbookExporter::class)->export([
        'budget' => [
            'fiscal_year' => 2026, 'responsible_unit_code' => '2112102003', 'responsible_unit_name' => 'Dirección',
            'budget_program_code' => 'E062', 'budget_program_name' => 'Formación', 'component_code' => 'C01',
            'component_name' => 'Servicios',
        ],
        'reconciliation' => ['groups' => [[
            'activity_code' => 'A01', 'activity_name' => 'Operación', 'specific_item_code' => '021103',
            'specific_item_name' => 'Material', 'month' => 4, 'target_amount_cents' => '125000',
        ]]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'abpre-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('ABRPRE-01')
        ->and($sheet->getCell('A6')->getValue())->toBe('Clave Unidad Responsable')
        ->and($sheet->getCell('I7')->getValue())->toBe('02-001')
        ->and($sheet->getCell('L7')->getValue())->toBe('021103')
        ->and($sheet->getCell('P7')->getValue())->toBe(1250)
        ->and($sheet->getCell('Y7')->getValue())->toBe(1250);
});
