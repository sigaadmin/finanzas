<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WorkSheetWorkbookExporter
{
    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('HOJA FINAL');
        $sheet->fromArray([['CENTRO REGIONAL DE EDUCACIÓN NORMAL / FELIPE CARRILLO PUERTO, QUINTANA ROO'], [], ['Actividad', 'Partida', 'Región', 'Nombre de la región', 'Presupuesto']]);
        foreach ($snapshot['reconciliation']['groups'] ?? [] as $index => $group) {
            $sheet->fromArray([[$group['activity_code'] ?? '', $group['specific_item_code'], '02-001', 'FELIPE CARRILLO PUERTO', ((int) $group['target_amount_cents']) / 100]], null, 'A'.($index + 4));
        }
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-work-sheet');
        (new Xlsx($book))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new \RuntimeException('No fue posible generar la Hoja de trabajo.');
    }
}
