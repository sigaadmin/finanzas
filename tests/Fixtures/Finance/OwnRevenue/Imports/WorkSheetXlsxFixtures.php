<?php

require_once __DIR__.'/OwnRevenueXlsxFixtureFactory.php';

/** @return array<string, string> */
function workSheetMonths(string $january = '0', string $february = '0'): array
{
    return [
        'G' => $january,
        'H' => $february,
        'I' => '0',
        'J' => '0',
        'K' => '0',
        'L' => '0',
        'M' => '0',
        'N' => '0',
        'O' => '0',
        'P' => '0',
        'Q' => '0',
        'R' => '0',
    ];
}

/** @param array<int, array<string, string|array{value?: string|null, formula?: string, type?: string}>> $dataRows */
function workSheetParserFixture(array $dataRows, int $firstHeaderRow = 3): string
{
    $headerRows = [
        $firstHeaderRow => [
            'A' => 'Actividades / Unidad de Presupuestación',
            'B' => 'Insumos',
            'C' => 'Partida',
            'D' => 'Región',
            'E' => 'Nombre de la región',
            'F' => 'Presupuesto',
            'G' => 'Calendario',
        ],
        $firstHeaderRow + 1 => [
            'G' => 'Enero', 'H' => 'Febrero', 'I' => 'Marzo', 'J' => 'Abril',
            'K' => 'Mayo', 'L' => 'Junio', 'M' => 'Julio', 'N' => 'Agosto',
            'O' => 'Septiembre', 'P' => 'Octubre', 'Q' => 'Noviembre',
            'R' => 'Diciembre', 'S' => 'Anual',
        ],
    ];

    return OwnRevenueXlsxFixtureFactory::create([
        'PORTADA' => [1 => ['A' => 'Documento de apoyo']],
        'HOJA FINAL' => $headerRows + $dataRows,
    ]);
}
