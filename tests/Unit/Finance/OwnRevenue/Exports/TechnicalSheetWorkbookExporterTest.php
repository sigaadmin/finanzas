<?php

use App\Services\Finance\OwnRevenue\Exports\TechnicalSheetWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports a technical sheet with the institutional header and required columns', function () {
    $contents = app(TechnicalSheetWorkbookExporter::class)->export([
        'budget' => [
            'fiscal_year' => 2026,
            'responsible_unit_code' => '2330',
            'responsible_unit_name' => 'Dirección de Instituciones Formadoras de Docentes',
            'budget_program_code' => 'E016',
            'component_code' => 'C01',
            'component_name' => 'Servicio Educativo de las instituciones formadoras docentes, brindado',
        ],
        'technical_needs' => [[
            'activity' => 'A03-A01', 'activity_name' => 'Formación docente', 'item' => '21103',
            'item_name' => 'Material de oficina', 'description' => 'Papel bond', 'quantity' => '2.0000',
            'unit' => 'Paquete', 'unit_price_cents' => '12550', 'amount_cents' => '25100',
            'month' => 4, 'impact_on_goals' => 'Atender 30 estudiantes',
            'region_code' => '02-001', 'region_name' => 'Felipe Carrillo Puerto',
        ]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'technical-test');
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    expect($sheet->getTitle())->toBe('FICHA TÉCNICA')
        ->and($sheet->getCell('A11')->getValue())->toBe('CLAVE UR')
        ->and($sheet->getCell('A12')->getValue())->toBe(2330)
        ->and($sheet->getCell('D11')->getValue())->toBe('NOMBRE')
        ->and($sheet->getCell('D12')->getValue())->toBe('Dirección de Instituciones Formadoras de Docentes')
        ->and($sheet->getCell('A13')->getValue())->toBe('CLAVE')
        ->and($sheet->getCell('A14')->getValue())->toBe('E016C0100000')
        ->and($sheet->getCell('D14')->getValue())->toBe('Servicio Educativo de las instituciones formadoras docentes, brindado')
        ->and($sheet->getCell('A16')->getValue())->toBe('Actividad')
        ->and($sheet->getCell('B16')->getValue())->toBe('Partida')
        ->and($sheet->getCell('J16')->getValue())->toBe('Mes presupuestado')
        ->and($sheet->getCell('A17')->getValue())->toBe('A03-A01')
        ->and($sheet->getCell('B17')->getValue())->toBe(21103)
        ->and($sheet->getCell('C17')->getValue())->toBe(2.0)
        ->and($sheet->getCell('D17')->getValue())->toBe('Paquete')
        ->and($sheet->getCell('E17')->getValue())->toBe('Papel bond')
        ->and($sheet->getCell('F17')->getValue())->toBe('02-001')
        ->and($sheet->getCell('G17')->getValue())->toBe('Felipe Carrillo Puerto')
        ->and($sheet->getCell('H17')->getValue())->toBe(125.5)
        ->and($sheet->getCell('I17')->getValue())->toBe(251)
        ->and($sheet->getCell('J17')->getValue())->toBe('04 - ABRIL')
        ->and($sheet->getCell('K16')->getValue())->toBeNull();
});
