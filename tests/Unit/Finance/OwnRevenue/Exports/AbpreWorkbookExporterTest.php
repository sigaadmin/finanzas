<?php

use App\Services\Finance\OwnRevenue\Exports\AbpreWorkbookExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;

test('it exports consolidated institutional ABPRE lines with the official activity and numeric COG codes', function () {
    $contents = app(AbpreWorkbookExporter::class)->export([
        'budget' => [
            'fiscal_year' => 2026, 'responsible_unit_code' => '2330', 'responsible_unit_name' => 'Dirección',
            'budget_program_code' => 'E062', 'budget_program_name' => 'Formación', 'component_code' => 'C01',
            'component_name' => 'Servicios',
            'official_activity_code' => 'A03',
            'official_activity_name' => 'Prestación del Servicio Educativo en el CREN Felipe Carrillo Puerto',
        ],
        'reconciliation' => ['groups' => [
            ['activity_code' => 'A03-A01', 'activity_name' => 'Subactividad 1', 'specific_item_code' => '21101', 'month' => 4, 'target_amount_cents' => '125000'],
            ['activity_code' => 'A03-A02', 'activity_name' => 'Subactividad 2', 'specific_item_code' => '21101', 'month' => 4, 'target_amount_cents' => '25000'],
            ['activity_code' => 'A03-A02', 'activity_name' => 'Subactividad 2', 'specific_item_code' => '21101', 'month' => 5, 'target_amount_cents' => '50000'],
            ['activity_code' => 'A03-A04', 'activity_name' => 'Subactividad 4', 'specific_item_code' => '31501', 'month' => 6, 'target_amount_cents' => '75000'],
        ]],
        'expense_classifications' => [
            '21101' => [
                'chapter_code' => '2000',
                'chapter_name' => 'Materiales y suministros',
                'specific_item_name' => 'Materiales, útiles y equipos menores de oficina',
            ],
            '31501' => [
                'chapter_code' => '3000',
                'chapter_name' => 'Servicios generales',
                'specific_item_name' => 'Telefonía celular',
            ],
        ],
        'justifications' => [[
            'specific_item_code' => '21101',
            'goals_impact' => 'Fortalece la operación académica.',
            'justification' => 'Se requiere para la atención administrativa.',
        ]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'abpre-test');
    file_put_contents($path, $contents);
    $workbook = IOFactory::load($path);
    $sheet = $workbook->getActiveSheet();
    $justificationSheet = $workbook->getSheetByName('Formato Justificación Partidas');
    unlink($path);

    expect($sheet->getTitle())->toBe('ABRPRE-01')
        ->and($sheet->getCell('A6')->getValue())->toBe('Clave Unidad Responsable')
        ->and($sheet->getCell('A7')->getValue())->toBe(2330)
        ->and($sheet->getCell('A7')->getDataType())->toBe('n')
        ->and($sheet->getCell('G7')->getValue())->toBe('A03')
        ->and($sheet->getCell('H7')->getValue())->toBe('Prestación del Servicio Educativo en el CREN Felipe Carrillo Puerto')
        ->and($sheet->getCell('I7')->getValue())->toBe('02-001')
        ->and($sheet->getCell('K7')->getValue())->toBe(2100)
        ->and($sheet->getCell('L7')->getValue())->toBe(21101)
        ->and($sheet->getCell('L7')->getDataType())->toBe('n')
        ->and($sheet->getCell('P7')->getValue())->toBe(1500.0)
        ->and($sheet->getCell('Q7')->getValue())->toBe(500.0)
        ->and($sheet->getCell('Y7')->getValue())->toBe(2000)
        ->and($sheet->getCell('K8')->getValue())->toBe(3100)
        ->and($sheet->getCell('L8')->getValue())->toBe(31501)
        ->and($sheet->getCell('R8')->getValue())->toBe(750.0)
        ->and($sheet->getCell('Y8')->getValue())->toBe(750)
        ->and($sheet->getHighestRow())->toBe(8)
        ->and($justificationSheet)->not->toBeNull()
        ->and($justificationSheet->getCell('B6')->getValue())->toBe('Unidad Responsable')
        ->and($justificationSheet->getCell('B7')->getValue())->toBe('Dirección')
        ->and($justificationSheet->getCell('C7')->getValue())->toBe(2000)
        ->and($justificationSheet->getCell('D7')->getValue())->toBe('Materiales y suministros')
        ->and($justificationSheet->getCell('E7')->getValue())->toBe(21101)
        ->and($justificationSheet->getCell('F7')->getValue())->toBe('Materiales, útiles y equipos menores de oficina')
        ->and($justificationSheet->getCell('G7')->getValue())->toBe('E062')
        ->and($justificationSheet->getCell('H7')->getValue())->toBe('Servicios')
        ->and($justificationSheet->getCell('I7')->getValue())->toBe('Fortalece la operación académica.')
        ->and($justificationSheet->getCell('J7')->getValue())->toBe('Se requiere para la atención administrativa.')
        ->and($justificationSheet->getCell('C8')->getValue())->toBe(3000)
        ->and($justificationSheet->getCell('E8')->getValue())->toBe(31501)
        ->and($justificationSheet->getCell('I8')->getValue())->toBeNull()
        ->and($justificationSheet->getCell('J8')->getValue())->toBeNull();
});
