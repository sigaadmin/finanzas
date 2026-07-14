<?php

use App\Services\Finance\OwnRevenue\Imports\AbpreWorkbookParser;
use App\Services\Finance\OwnRevenue\Imports\XlsxWorkbookReader;

require_once __DIR__.'/../../../../Fixtures/Finance/OwnRevenue/Imports/OwnRevenueXlsxFixtureFactory.php';

function abpreParserFixture(): string
{
    $headers = [
        'A' => 'Clave Unidad Responsable', 'B' => 'Nombre de Unidad Responsable', 'C' => 'Programa Presupuestario',
        'D' => 'Nombre Programa Presupuestario', 'E' => 'Clave Componente', 'F' => 'Nombre Componente',
        'G' => 'Clave Actividad', 'H' => 'Nombre Actividad', 'I' => 'Clave Región', 'J' => 'Nombre de la Región',
        'K' => 'Concepto Especifico del Gasto', 'L' => 'Partida', 'M' => 'Enero', 'N' => 'Febrero',
        'O' => 'Marzo', 'P' => 'Abril', 'Q' => 'Mayo', 'R' => 'Junio', 'S' => 'Julio', 'T' => 'Agosto',
        'U' => 'Septiembre', 'V' => 'Octubre', 'W' => 'Noviembre', 'X' => 'Diciembre', 'Y' => 'Anual',
    ];
    $institution = [
        'A' => '2330', 'B' => 'Dirección de instituciones formadoras de docentes', 'C' => 'E016',
        'D' => 'Formación docente', 'E' => 'C01', 'F' => 'Servicio educativo', 'G' => 'A03',
        'H' => 'Prestación del servicio', 'I' => '04-001', 'J' => 'OTRA REGIÓN', 'K' => '2100',
    ];
    $zeros = ['M' => '0', 'N' => '0', 'O' => '0', 'Q' => '0', 'R' => '0', 'S' => '0', 'T' => '0', 'U' => '0', 'V' => '0', 'W' => '0', 'X' => '0'];

    return OwnRevenueXlsxFixtureFactory::create([
        'ABRPRE-01' => [
            2 => ['A' => 'Proyecto de Presupuesto 2026'],
            8 => $headers,
            9 => [...$institution, 'L' => '21101', ...$zeros, 'P' => '1022', 'Y' => '1000'],
            10 => ['K' => '2100', 'L' => '21101', ...$zeros, 'P' => '28', 'Y' => '28'],
            11 => [...$institution, 'A' => '9999', 'L' => '21101', ...$zeros, 'P' => '5', 'Y' => '5'],
            12 => [...$institution, 'I' => '02-001', 'L' => '99999', ...$zeros, 'P' => '10', 'Y' => '10'],
            13 => [...$institution, 'I' => '02-001', 'L' => '21101', ...$zeros, 'P' => 'importe inválido', 'Y' => '0'],
        ],
        'Formato Justificación Partidas' => [
            7 => ['B' => 'Unidad Responble', 'C' => 'Capítulo', 'D' => 'Descripción Capítulo', 'E' => 'Partida', 'F' => 'Descripción Partida', 'G' => 'Programa Prresupuestario', 'H' => 'Componente', 'I' => 'Impacto en Metas', 'J' => 'Justificación'],
            8 => ['B' => 'Dirección', 'C' => '2000', 'D' => 'Materiales', 'E' => '21101', 'F' => 'Papelería', 'G' => 'E016', 'H' => 'Servicio educativo', 'I' => 'Impacto', 'J' => 'Necesario'],
        ],
    ]);
}

test('ABPRE parser forward fills groups regions and converts pesos to exact cents', function () {
    $analysis = (new AbpreWorkbookParser)->parse(
        (new XlsxWorkbookReader)->read(abpreParserFixture()),
        ['fiscal_year' => 2027, 'responsible_unit_code' => '2330'],
        ['21101' => 10],
    );

    $codes = array_map(fn ($issue): string => $issue->code, $analysis->issues);

    expect($analysis->lines)->toHaveCount(2)
        ->and($analysis->lines[0]->responsibleUnitCode)->toBe('2330')
        ->and($analysis->lines[0]->specificItemCode)->toBe('21101')
        ->and($analysis->lines[0]->regionCode)->toBe('02-001')
        ->and($analysis->lines[0]->months[4])->toBe('105000')
        ->and($analysis->lines[0]->annualAmountCents)->toBe('105000')
        ->and($analysis->lines[0]->sourceRows)->toBe([9, 10])
        ->and($analysis->justifications)->toHaveCount(1)
        ->and($codes)->toContain(
            'region.normalized',
            'year.mismatch',
            'cog.missing_item',
            'abpre.annual_mismatch',
            'amount.invalid',
            'abpre.other_unit',
            'abpre.missing_justification',
        );
});
