<?php

use App\Services\Finance\OwnRevenue\Exports\FuelWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports one fuel sheet with route calculation and april budget month', function () {
    $contents = app(FuelWorkbookExporter::class)->export(['fuel_needs' => [[
        'activity' => 'A02', 'commission_date_label' => '12 de marzo', 'operational_month' => 3,
        'reason' => 'Supervisión', 'vehicle_model' => 'Hilux', 'kilometers_per_liter' => '10.0000',
        'outbound_origin' => 'FCP', 'outbound_destination' => 'Chetumal', 'outbound_kilometers' => '160.0000',
        'return_origin' => 'Chetumal', 'return_destination' => 'FCP', 'return_kilometers' => '160.0000',
        'additional_kilometers' => '20.0000', 'total_kilometers' => '340.0000', 'liters' => '34.0000',
        'fuel_price' => '25.0000', 'mathematical_amount_cents' => '85000', 'rounded_amount_cents' => '85000',
        'amount_cents' => '85000',
    ]]]);
    $path = tempnam(sys_get_temp_dir(), 'fuel-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('COMBUSTIBLE')
        ->and($sheet->getCell('A1')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('D2')->getValue())->toBe(4)
        ->and($sheet->getCell('N2')->getValue())->toEqual(340)
        ->and($sheet->getCell('S2')->getValue())->toBe(850);
});
