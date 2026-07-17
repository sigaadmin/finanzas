<?php

use App\Services\Finance\OwnRevenue\Exports\WorkSheetWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports the final work sheet with region and monthly calendar', function () {
    $contents = app(WorkSheetWorkbookExporter::class)->export(['reconciliation' => ['groups' => [[
        'activity_code' => 'A01', 'specific_item_code' => '21103', 'specific_item_name' => 'Material',
        'month' => 4, 'target_amount_cents' => '125000',
    ]]]]);
    $path = tempnam(sys_get_temp_dir(), 'work-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('HOJA FINAL')
        ->and($sheet->getCell('A3')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('D5')->getValue())->toBe('02-001')
        ->and($sheet->getCell('J5')->getValue())->toBe(1250)
        ->and($sheet->getCell('S5')->getValue())->toBe(1250);
});
