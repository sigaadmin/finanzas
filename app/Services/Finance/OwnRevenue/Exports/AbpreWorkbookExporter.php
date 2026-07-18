<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        $groups = $this->consolidatedGroups($snapshot['reconciliation']['groups'] ?? []);
        foreach ($groups as $index => $group) {
            $row = $index + 7;
            $budget = $snapshot['budget'] ?? [];
            $sheet->fromArray([[
                (int) ($budget['responsible_unit_code'] ?? 0), $budget['responsible_unit_name'] ?? '',
                $budget['budget_program_code'] ?? '', $budget['budget_program_name'] ?? '',
                $budget['component_code'] ?? '', $budget['component_name'] ?? '',
                $budget['official_activity_code'] ?? ($group['activity_code'] ?? ''),
                $budget['official_activity_name'] ?? ($group['activity_name'] ?? ''),
                '02-001', 'Felipe Carrillo Puerto', $this->conceptCode($group['specific_item_code']), (int) $group['specific_item_code'],
                ...array_map(fn (int $amountCents): float => $amountCents / 100, $group['months']), $group['annual_amount_cents'] / 100,
            ]], null, 'A'.$row);
            foreach (['C', 'E', 'G', 'I'] as $column) {
                $sheet->setCellValueExplicit($column.$row, (string) $sheet->getCell($column.$row)->getValue(), DataType::TYPE_STRING);
            }
        }
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A6:Y'.$sheet->getHighestRow());
        $this->addJustificationSheet($spreadsheet, $snapshot, $groups);
        $spreadsheet->setActiveSheetIndex(0);
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

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array{specific_item_code: string, activity_code: string, activity_name: string, months: list<int>, annual_amount_cents: int}>
     */
    private function consolidatedGroups(array $groups): array
    {
        $consolidated = [];
        foreach ($groups as $group) {
            $specificItemCode = (string) ($group['specific_item_code'] ?? '');
            if ($specificItemCode === '') {
                continue;
            }
            $consolidated[$specificItemCode] ??= [
                'specific_item_code' => $specificItemCode,
                'activity_code' => (string) ($group['activity_code'] ?? ''),
                'activity_name' => (string) ($group['activity_name'] ?? ''),
                'months' => array_fill(0, 12, 0),
                'annual_amount_cents' => 0,
            ];
            $month = (int) ($group['month'] ?? 0);
            $amountCents = (int) ($group['target_amount_cents'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $consolidated[$specificItemCode]['months'][$month - 1] += $amountCents;
            }
            $consolidated[$specificItemCode]['annual_amount_cents'] += $amountCents;
        }
        uksort($consolidated, fn (string $left, string $right): int => (int) $left <=> (int) $right);

        return array_values($consolidated);
    }

    private function conceptCode(string $specificItemCode): int
    {
        return (int) (substr(str_pad($specificItemCode, 2, '0'), 0, 2).'00');
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<array{specific_item_code: string, activity_code: string, activity_name: string, months: list<int>, annual_amount_cents: int}>  $groups
     */
    private function addJustificationSheet(Spreadsheet $spreadsheet, array $snapshot, array $groups): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Formato Justificación Partidas');
        $sheet->mergeCells('B2:J2');
        $sheet->setCellValue('B2', 'Formato para justificar las partidas del Presupuesto de Egresos '.($snapshot['budget']['fiscal_year'] ?? ''));
        $sheet->fromArray([[
            'Unidad Responsable', 'Capítulo', 'Descripción Capítulo', 'Partida', 'Descripción de la partida',
            'Programa Presupuestario', 'Componente', 'Impacto en meta', 'Justificación',
        ]], null, 'B6');

        $classifications = $snapshot['expense_classifications'] ?? [];
        $justifications = $this->justificationsByItem($snapshot['justifications'] ?? []);
        $budget = $snapshot['budget'] ?? [];
        foreach ($groups as $index => $group) {
            $classification = $classifications[$group['specific_item_code']] ?? [];
            $justification = $justifications[$group['specific_item_code']] ?? [];
            $sheet->fromArray([[
                $budget['responsible_unit_name'] ?? null,
                (int) ($classification['chapter_code'] ?? $this->chapterCode($group['specific_item_code'])),
                $classification['chapter_name'] ?? null,
                (int) $group['specific_item_code'],
                $classification['specific_item_name'] ?? null,
                $budget['budget_program_code'] ?? null,
                $budget['component_name'] ?? null,
                $justification['goals_impact'] ?? null,
                $justification['justification'] ?? null,
            ]], null, 'B'.($index + 7));
        }

        $lastRow = $sheet->getHighestRow();
        $sheet->freezePane('B7');
        $sheet->setAutoFilter('B6:J'.$lastRow);
        $sheet->getStyle('B2:J2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('B2:J2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B6:J6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('B6:J6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF600D04');
        $sheet->getStyle('B6:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('B6:J'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('C7:C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E7:E'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['B' => 28, 'C' => 12, 'D' => 26, 'E' => 14, 'F' => 34, 'G' => 18, 'H' => 34, 'I' => 52, 'J' => 60] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getRowDimension(6)->setRowHeight(30);
        foreach (range(7, $lastRow) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(65);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $justifications
     * @return array<string, array<string, mixed>>
     */
    private function justificationsByItem(array $justifications): array
    {
        $byItem = [];
        foreach ($justifications as $justification) {
            $specificItemCode = (string) ($justification['specific_item_code'] ?? '');
            if ($specificItemCode !== '') {
                $byItem[$specificItemCode] = $justification;
            }
        }

        return $byItem;
    }

    private function chapterCode(string $specificItemCode): int
    {
        return (int) (substr($specificItemCode, 0, 1).'000');
    }
}
