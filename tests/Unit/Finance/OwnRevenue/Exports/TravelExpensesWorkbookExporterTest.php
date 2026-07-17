<?php

use App\Services\Finance\OwnRevenue\Exports\TravelExpensesWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports one travel row per participant with commission data', function () {
    $contents = app(TravelExpensesWorkbookExporter::class)->export(['travel_commissions' => [[
        'activity' => 'A03', 'commission_date_label' => '5 al 7 de junio', 'month' => 6,
        'reason' => 'Capacitación', 'destination' => 'Mérida', 'food_zone' => 'B', 'lodging_zone' => 'B',
        'uma_value' => '117.3100', 'flight_amount_cents' => '500000',
        'participants' => [[
            'person_name' => 'Ana Pérez', 'position' => 'Docente', 'commission_days' => '3.0000',
            'per_diem_uma' => '8.0000', 'lodging_uma' => '10.0000',
            'per_diem_amount_cents' => '281544', 'lodging_amount_cents' => '351930', 'amount_cents' => '633474',
        ], [
            'person_name' => 'Luis López', 'position' => 'Director', 'commission_days' => '3.0000',
            'per_diem_uma' => '9.0000', 'lodging_uma' => '10.0000',
            'per_diem_amount_cents' => '316737', 'lodging_amount_cents' => '351930', 'amount_cents' => '668667',
        ]],
    ]]]);
    $path = tempnam(sys_get_temp_dir(), 'travel-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('VIÁTICOS')
        ->and($sheet->getCell('A1')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('F2')->getValue())->toBe('Ana Pérez')
        ->and($sheet->getCell('F3')->getValue())->toBe('Luis López')
        ->and($sheet->getCell('P2')->getValue())->toBe(5000)
        ->and($sheet->getCell('R2')->getValue())->toBe('02-001');
});
