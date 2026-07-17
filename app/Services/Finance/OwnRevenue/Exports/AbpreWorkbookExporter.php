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
        $sheet->setTitle('ABRPRE-01');
        $sheet->setCellValue('A1', 'PRESUPUESTO DE EGRESOS '.($snapshot['budget']['fiscal_year'] ?? ''));
        $sheet->setCellValue('A2', 'ABPRE-01 — CALENDARIZACIÓN POR PARTIDA');
        $sheet->fromArray([[
            'Clave Unidad Responsable', 'Nombre UR', 'Programa Presupuestario', 'Nombre Programa',
            'Clave Componente', 'Nombre Componente', 'Clave Actividad', 'Nombre Actividad',
            'Clave Región', 'Nombre Región', 'Concepto Específico del Gasto', 'Partida',
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
            'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Anual',
        ]], null, 'A6');
        foreach ($snapshot['reconciliation']['groups'] ?? [] as $index => $group) {
            $row = $index + 7;
            $budget = $snapshot['budget'] ?? [];
            $sheet->fromArray([[
                $budget['responsible_unit_code'] ?? '', $budget['responsible_unit_name'] ?? '',
                $budget['budget_program_code'] ?? '', $budget['budget_program_name'] ?? '',
                $budget['component_code'] ?? '', $budget['component_name'] ?? '',
                $group['activity_code'] ?? ($budget['official_activity_code'] ?? ''),
                $group['activity_name'] ?? ($budget['official_activity_name'] ?? ''),
                '02-001', 'Felipe Carrillo Puerto', $group['specific_item_name'] ?? '', '',
            ]], null, 'A'.$row);
            foreach (['A', 'C', 'E', 'G', 'I'] as $column) {
                $sheet->setCellValueExplicit($column.$row, (string) $sheet->getCell($column.$row)->getValue(), DataType::TYPE_STRING);
            }
            $sheet->setCellValueExplicit('L'.$row, (string) $group['specific_item_code'], DataType::TYPE_STRING);
            $amount = ((int) $group['target_amount_cents']) / 100;
            $month = max(1, min(12, (int) $group['month']));
            $sheet->setCellValue([$month + 12, $row], $amount);
            $sheet->setCellValue('Y'.$row, $amount);
        }
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A6:Y'.$sheet->getHighestRow());
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
