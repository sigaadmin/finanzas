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
        $sheet->fromArray([['CENTRO REGIONAL DE EDUCACIÓN NORMAL / FELIPE CARRILLO PUERTO, QUINTANA ROO'], [], [
            'Actividad', 'Insumos', 'Partida', 'Región', 'Nombre región', 'Presupuesto', 'Calendario',
        ], ['', '', '', '', '', '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Anual']]);
        foreach ($snapshot['reconciliation']['groups'] ?? [] as $index => $group) {
            $row = $index + 5;
            $amount = ((int) $group['target_amount_cents']) / 100;
            $sheet->fromArray([[
                $group['activity_code'] ?? '', $group['specific_item_name'] ?? '',
                $group['specific_item_code'], '02-001', 'FELIPE CARRILLO PUERTO', $amount,
            ]], null, 'A'.$row);
            $month = max(1, min(12, (int) $group['month']));
            $sheet->setCellValue([$month + 6, $row], $amount);
            $sheet->setCellValue('S'.$row, $amount);
        }
        $sheet->freezePane('A5');
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-work-sheet');
        (new Xlsx($book))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new \RuntimeException('No fue posible generar la Hoja de trabajo.');
    }
}
