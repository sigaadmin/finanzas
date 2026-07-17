<?php

use App\Services\Finance\OwnRevenue\Exports\TechnicalSheetWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports one technical sheet with activity details and exact total', function () {
    $contents = app(TechnicalSheetWorkbookExporter::class)->export([
        'technical_needs' => [[
            'activity' => 'A01', 'activity_name' => 'Formación docente', 'item' => '21103',
            'item_name' => 'Material de oficina', 'description' => 'Papel bond', 'quantity' => '2.0000',
            'unit' => 'Paquete', 'unit_price_cents' => '12550', 'amount_cents' => '25100',
            'month' => 5, 'impact_on_goals' => 'Atender 30 estudiantes',
            'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
        ]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'technical-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('FICHA TÉCNICA')
        ->and($sheet->getCell('A1')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('D2')->getValue())->toBe('Material de oficina')
        ->and($sheet->getCell('I2')->getValue())->toBe(251)
        ->and($sheet->getCell('L2')->getValue())->toBe('02-001');
});
