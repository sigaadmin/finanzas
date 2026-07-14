<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Services\Finance\OwnRevenue\Imports\OwnRevenueWorkbookFormatDetector;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

function ownRevenueFixture(array $headers, int $year): string
{
    $cells = ['A' => "Proyecto de Presupuesto {$year}"];

    foreach ($headers as $index => $header) {
        $column = chr(65 + $index);
        $cells[$column] = $header;
    }

    return OwnRevenueXlsxFixtureFactory::create(['FICHA' => [2 => ['A' => "Ejercicio {$year}"], 3 => $cells]]);
}

dataset('own revenue workbook fixtures', [
    'ABPRE' => [[
        'Clave Unidad Responsable', 'Partida', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Anual',
    ], OwnRevenueImportFormat::Abpre, 2027],
    'work sheet' => [[
        'actividad', 'concepto', 'partida', 'región', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre', 'anual',
    ], OwnRevenueImportFormat::WorkSheet, 2026],
    'technical sheet' => [[
        'Partida', 'Cantidad', 'Unidad', 'Descripción', 'Ragión', 'Costo', 'Mes Presupuestado',
    ], OwnRevenueImportFormat::TechnicalSheet, 2027],
    'fuel' => [[
        'FECHAS DE LA COMISION', 'MODELO DE VEHÍCULO', 'RECORRIDO', 'IMPORTE',
    ], OwnRevenueImportFormat::Fuel, 2026],
    'travel expenses' => [[
        'FECHAS DE LA COMISION', 'NOMBRE DE PERSONAL COMISIONADO', 'COSTO UMA', 'VIATICOS', 'HOSPEDAJE',
    ], OwnRevenueImportFormat::TravelExpenses, 2027],
]);

it('detects each workbook by normalized headers', function (array $headers, OwnRevenueImportFormat $format, int $year) {
    $detection = (new OwnRevenueWorkbookFormatDetector)->detect(
        (new XlsxWorkbookReader)->read(ownRevenueFixture($headers, $year)),
    );

    expect($detection->format)->toBe($format)
        ->and($detection->confidence)->toBeGreaterThanOrEqual(80)
        ->and($detection->detectedYear)->toBe($year)
        ->and($detection->evidence)->not->toBeEmpty();
})->with('own revenue workbook fixtures');

it('does not choose a format when strong signatures are ambiguous', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'FICHA' => [
            3 => ['A' => 'FECHAS DE LA COMISION', 'B' => 'MODELO DE VEHÍCULO', 'C' => 'RECORRIDO', 'D' => 'IMPORTE'],
            4 => ['A' => 'FECHAS DE LA COMISION', 'B' => 'NOMBRE DE PERSONAL COMISIONADO', 'C' => 'COSTO UMA', 'D' => 'VIATICOS', 'E' => 'HOSPEDAJE'],
        ],
    ]);
    $detection = (new OwnRevenueWorkbookFormatDetector)->detect((new XlsxWorkbookReader)->read($fixture));

    expect($detection->format)->toBeNull()
        ->and($detection->evidence)->not->toBeEmpty();
});

it('detects a work sheet whose headers span two rows and use institutional labels', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'HOJA FINAL' => [
            3 => [
                'A' => 'ACTIVIDADES UNIDAD DE PRESUPUESTACIÓN',
                'B' => 'INSUMOS',
                'C' => 'PARTIDA',
                'D' => 'REGIÓN',
                'E' => 'NOMBRE DE LA REGIÓN',
                'F' => 'PRESUPUESTO',
                'G' => 'CALENDARIO',
            ],
            4 => [
                'G' => 'ENERO', 'H' => 'FEBRERO', 'I' => 'MARZO', 'J' => 'ABRIL',
                'K' => 'MAYO', 'L' => 'JUNIO', 'M' => 'JULIO', 'N' => 'AGOSTO',
                'O' => 'SEPTIEMBRE', 'P' => 'OCTUBRE', 'Q' => 'NOVIEMBRE',
                'R' => 'DICIEMBRE', 'S' => 'ANUAL',
            ],
        ],
    ]);

    $detection = (new OwnRevenueWorkbookFormatDetector)->detect((new XlsxWorkbookReader)->read($fixture));

    expect($detection->format)->toBe(OwnRevenueImportFormat::WorkSheet)
        ->and($detection->confidence)->toBe(100);
});

it('detects years from budget labels instead of repeated COG chapter codes', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'ABPRE' => [
            2 => ['A' => 'Formato para el Presupuesto de Egresos 2026'],
            3 => ['A' => 'Clave Unidad Responsable', 'B' => 'Partida', 'C' => 'Enero', 'D' => 'Febrero', 'E' => 'Marzo', 'F' => 'Abril', 'G' => 'Mayo', 'H' => 'Junio', 'I' => 'Julio', 'J' => 'Agosto', 'K' => 'Septiembre', 'L' => 'Octubre', 'M' => 'Noviembre', 'N' => 'Diciembre', 'O' => 'Anual'],
            4 => ['A' => '2000'],
            5 => ['A' => '2000'],
            6 => ['A' => '2000'],
        ],
    ]);

    $detection = (new OwnRevenueWorkbookFormatDetector)->detect((new XlsxWorkbookReader)->read($fixture));

    expect($detection->detectedYear)->toBe(2026);
});

it('does not merge header fragments separated by blank physical rows', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'HOJA FINAL' => [
            3 => [
                'A' => 'ACTIVIDADES UNIDAD DE PRESUPUESTACIÓN',
                'B' => 'INSUMOS',
                'C' => 'PARTIDA',
                'D' => 'REGIÓN',
            ],
            100 => [
                'G' => 'ENERO', 'H' => 'FEBRERO', 'I' => 'MARZO', 'J' => 'ABRIL',
                'K' => 'MAYO', 'L' => 'JUNIO', 'M' => 'JULIO', 'N' => 'AGOSTO',
                'O' => 'SEPTIEMBRE', 'P' => 'OCTUBRE', 'Q' => 'NOVIEMBRE',
                'R' => 'DICIEMBRE', 'S' => 'ANUAL',
            ],
        ],
    ]);

    $detection = (new OwnRevenueWorkbookFormatDetector)->detect((new XlsxWorkbookReader)->read($fixture));

    expect($detection->format)->not->toBe(OwnRevenueImportFormat::WorkSheet);
});
