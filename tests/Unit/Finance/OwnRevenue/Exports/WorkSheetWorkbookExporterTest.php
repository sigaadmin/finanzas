<?php

use App\Services\Finance\OwnRevenue\Exports\WorkSheetWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports a consolidated work sheet ordered by activity and item code', function () {
    $contents = app(WorkSheetWorkbookExporter::class)->export([
        'reconciliation' => ['groups' => [
            ['activity_code' => 'A03-A02', 'activity_name' => 'Profesorado y docencia', 'specific_item_code' => '37502', 'specific_item_name' => 'Viáticos', 'month' => 5, 'target_amount_cents' => '938123'],
            ['activity_code' => 'A03-A02', 'activity_name' => 'Profesorado y docencia', 'specific_item_code' => '37502', 'specific_item_name' => 'Viáticos', 'month' => 9, 'target_amount_cents' => '229583'],
            ['activity_code' => 'A03-A02', 'activity_name' => 'Profesorado y docencia', 'specific_item_code' => '21101', 'specific_item_name' => 'Material', 'month' => 4, 'target_amount_cents' => '50000'],
            ['activity_code' => 'A03-A01', 'activity_name' => 'Fomento de la investigación', 'specific_item_code' => '21101', 'specific_item_name' => 'Material', 'month' => 4, 'target_amount_cents' => '125000'],
        ]],
        'expense_classifications' => [
            '21101' => ['specific_item_name' => 'Materiales, útiles y equipos menores de oficina'],
            '37502' => ['specific_item_name' => 'Gastos de camino'],
        ],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'work-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('HOJA FINAL')
        ->and($sheet->getCell('A3')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('D5')->getValue())->toBe('02-001')
        ->and($sheet->getCell('A5')->getValue())->toBe('A03-A01 - Fomento de la investigación')
        ->and($sheet->getCell('B5')->getValue())->toBe('Materiales, útiles y equipos menores de oficina')
        ->and($sheet->getCell('C5')->getValue())->toBe(21101)
        ->and($sheet->getCell('J5')->getValue())->toBe(1250.0)
        ->and($sheet->getCell('S5')->getValue())->toBe(1250)
        ->and($sheet->getCell('A6')->getValue())->toBe('A03-A02 - Profesorado y docencia')
        ->and($sheet->getCell('C6')->getValue())->toBe(21101)
        ->and($sheet->getCell('A7')->getValue())->toBe('A03-A02 - Profesorado y docencia')
        ->and($sheet->getCell('B7')->getValue())->toBe('Gastos de camino')
        ->and($sheet->getCell('C7')->getValue())->toBe(37502)
        ->and($sheet->getCell('K7')->getValue())->toBe(9381.23)
        ->and($sheet->getCell('O7')->getValue())->toBe(2295.83)
        ->and($sheet->getCell('S7')->getValue())->toBe(11677.06)
        ->and($sheet->getHighestRow())->toBe(7);
});
