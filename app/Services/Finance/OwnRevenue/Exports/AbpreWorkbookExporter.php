<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class AbpreWorkbookExporter
{
    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ABPRE');
        $sheet->fromArray([
            ['Presupuesto de Ingresos Propios '.($snapshot['budget']['fiscal_year'] ?? '')],
            [($snapshot['budget']['region_code'] ?? '').' — '.($snapshot['budget']['region_name'] ?? '')],
            ['Partida', 'Mes', 'Importe'],
        ]);
        foreach ($snapshot['reconciliation']['groups'] ?? [] as $index => $group) {
            $row = $index + 4;
            $sheet->setCellValueExplicit('A'.$row, (string) $group['specific_item_code'], DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, $group['month']);
            $sheet->setCellValue('C'.$row, ((int) $group['target_amount_cents']) / 100);
        }
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-abpre');
        if ($path === false) {
            throw new RuntimeException('No fue posible preparar el archivo ABPRE.');
        }
        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        unlink($path);
        if ($contents === false) {
            throw new RuntimeException('No fue posible leer el archivo ABPRE.');
        }

        return $contents;
    }
}
