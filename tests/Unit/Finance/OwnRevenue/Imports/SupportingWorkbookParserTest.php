<?php

use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Services\Finance\OwnRevenue\Imports\SupportingWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

test('supporting formats are normalized into reviewable rows', function (
    OwnRevenueImportFormat $format,
    array $sheets,
    array $expected,
) {
    $fixture = OwnRevenueXlsxFixtureFactory::create($sheets);
    $workbook = (new XlsxWorkbookReader)->read($fixture);

    $analysis = (new SupportingWorkbookParser)->parse(
        $workbook,
        $format,
        ['21101' => 1],
    );

    expect($analysis->lines)->toHaveCount(1)
        ->and($analysis->lines[0]->values)->toMatchArray($expected)
        ->and($analysis->sourceRows)->toHaveCount(1)
        ->and($analysis->sourceRows[0]['row_number'])->toBe($format === OwnRevenueImportFormat::TechnicalSheet ? 17 : ($format === OwnRevenueImportFormat::Fuel ? 6 : 4));
})->with([
    'ficha técnica' => [
        OwnRevenueImportFormat::TechnicalSheet,
        [
            'Partida 21103' => [
                16 => ['A' => 'Partida', 'B' => '#', 'C' => 'Cantidad', 'D' => 'Unidad', 'E' => 'Descripción', 'F' => 'Ragión', 'G' => 'Nombre de la Región', 'H' => 'Costo', 'I' => 'Mes Presupuestado'],
                17 => ['A' => '21101', 'B' => '1', 'C' => '60', 'D' => 'PAQUETE', 'E' => 'Barras de silicón', 'F' => '04-001', 'G' => 'Chetumal', 'H' => '3300.25', 'I' => 'ABRIL'],
            ],
        ],
        [
            'specificItemCode' => '21101',
            'quantity' => '60',
            'unit' => 'PAQUETE',
            'description' => 'Barras de silicón',
            'regionCode' => '02-001',
            'regionName' => 'Felipe Carrillo Puerto',
            'amountCents' => '330025',
            'budgetMonth' => 4,
        ],
    ],
    'combustible' => [
        OwnRevenueImportFormat::Fuel,
        [
            'FICHA' => [
                5 => ['A' => '#', 'B' => 'FECHAS DE LA COMISION', 'C' => 'MES', 'D' => 'MOTIVO DE LA COMISIÓN', 'E' => 'MODELO DE VEHÍCULO', 'F' => 'RENDIMIENTO DE LITRO DE GASOLINA POR KILOMETRO', 'G' => 'LUGAR DONDE INICIA EL RECORRIDO', 'H' => 'LUGAR DONDE FINALIZA EL RECORRIDO', 'I' => 'KILOMETRAJE', 'J' => 'LUGAR DONDE INICIA EL RECORRIDO', 'K' => 'LUGAR DONDE FINALIZA EL RECORRIDO', 'L' => 'KILOMETRAJE2', 'M' => 'RECORRIDO', 'N' => 'COSTO DE COMBUSTIBLE X LITRO', 'O' => 'IMPORTE'],
                6 => ['B' => 'ABRIL', 'C' => '4', 'D' => 'Gestión administrativa', 'E' => 'PARTICULAR', 'F' => '10', 'G' => 'FELIPE CARRILLO PUERTO', 'H' => 'CHETUMAL', 'I' => '150', 'J' => 'CHETUMAL', 'K' => 'FELIPE CARRILLO PUERTO', 'L' => '150', 'M' => '30', 'N' => '24.03', 'O' => '793'],
                7 => ['D' => 'ELABORÓ'],
            ],
            'Hoja1' => [1 => ['A' => 'LOCALIDAD', 'B' => 'KMS']],
        ],
        [
            'month' => 4,
            'reason' => 'Gestión administrativa',
            'vehicleModel' => 'PARTICULAR',
            'kilometersPerLiter' => '10',
            'outboundOrigin' => 'FELIPE CARRILLO PUERTO',
            'outboundDestination' => 'CHETUMAL',
            'outboundKilometers' => '150',
            'returnKilometers' => '150',
            'liters' => '30',
            'fuelPrice' => '24.03',
            'amountCents' => '79300',
        ],
    ],
    'viáticos' => [
        OwnRevenueImportFormat::TravelExpenses,
        [
            'FICHA' => [
                3 => ['A' => 'FECHAS DE LA COMISION', 'B' => 'MES', 'C' => 'MOTIVO DE LA COMISIÓN', 'D' => 'NOMBRE DE PERSONAL COMISIONADO', 'E' => 'CARGO', 'F' => 'DIAS DE COMISIÓN', 'G' => 'LUGAR DE LA COMISIÓN', 'H' => 'VIATICOS', 'I' => 'HOSPEDAJE', 'J' => 'COSTO UMA', 'K' => 'VIATICOS2', 'L' => 'HOSPEDAJE3', 'M' => 'TOTAL', 'N' => 'AVION'],
                4 => ['A' => 'MAYO', 'B' => '5', 'C' => 'Estancia académica', 'D' => 'DOCENTE', 'E' => 'DOCENTE', 'F' => '0.5', 'G' => 'YUCATÁN', 'H' => '8', 'I' => '9', 'J' => '108.57', 'K' => '2606', 'L' => '2932', 'M' => '5538', 'N' => '9000'],
                5 => ['C' => 'ELABORÓ'],
            ],
            'TARIFAS VIÁTICOS' => [4 => ['A' => 'CARGO O FUNCIÓN', 'B' => 'ZONA I']],
        ],
        [
            'month' => 5,
            'reason' => 'Estancia académica',
            'personName' => 'DOCENTE',
            'position' => 'DOCENTE',
            'commissionDays' => '0.5',
            'destination' => 'YUCATÁN',
            'perDiemUma' => '8',
            'lodgingUma' => '9',
            'umaValue' => '108.57',
            'perDiemAmountCents' => '260600',
            'lodgingAmountCents' => '293200',
            'totalAmountCents' => '553800',
            'flightAmountCents' => '900000',
        ],
    ],
]);

test('technical sheet reports unknown items and region normalization', function () {
    $fixture = OwnRevenueXlsxFixtureFactory::create([
        'Partida 99999' => [
            1 => ['A' => 'Partida', 'B' => '#', 'C' => 'Cantidad', 'D' => 'Unidad', 'E' => 'Descripción', 'F' => 'Ragión', 'G' => 'Nombre de la Región', 'H' => 'Costo', 'I' => 'Mes Presupuestado'],
            2 => ['A' => '99999', 'B' => '1', 'C' => '1', 'D' => 'PIEZA', 'E' => 'Insumo', 'F' => '05-001', 'G' => 'Cancún', 'H' => '10', 'I' => 'MAYO'],
        ],
    ]);

    $analysis = (new SupportingWorkbookParser)->parse(
        (new XlsxWorkbookReader)->read($fixture),
        OwnRevenueImportFormat::TechnicalSheet,
        ['21101' => 1],
    );

    expect(array_column($analysis->issues, 'code'))
        ->toContain('cog.missing_item', 'region.normalized');
});
