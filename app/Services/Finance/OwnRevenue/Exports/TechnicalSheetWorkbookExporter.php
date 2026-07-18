<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TechnicalSheetWorkbookExporter
{
    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FICHA TÉCNICA');
        $budget = $snapshot['budget'] ?? [];
        $fiscalYear = $budget['fiscal_year'] ?? '';

        foreach ([2, 3, 4, 6, 8, 9] as $row) {
            $sheet->mergeCells("A{$row}:J{$row}");
        }
        $sheet->fromArray([
            ['SERVICIOS EDUCATIVOS DE QUINTANA ROO'],
            ['COORDINACIÓN GENERAL DE ADMINISTRACIÓN Y FINANZAS'],
            ['DIRECCIÓN DE PRESUPUESTO'],
        ], null, 'A2');
        $sheet->setCellValue('A6', 'CENTRO REGIONAL DE EDUCACIÓN NORMAL / FELIPE CARRILLO PUERTO, QUINTANA ROO');
        $sheet->setCellValue('A8', "FICHA TÉCNICA PARA LA INTEGRACIÓN DEL PROYECTO DE PRESUPUESTO {$fiscalYear}\nPOR PARTIDA PRESUPUESTAL");

        $sheet->mergeCells('A11:C11');
        $sheet->mergeCells('D11:J11');
        $sheet->mergeCells('A12:C12');
        $sheet->mergeCells('D12:J12');
        $sheet->mergeCells('A13:C13');
        $sheet->mergeCells('D13:J13');
        $sheet->mergeCells('A14:C14');
        $sheet->mergeCells('D14:J14');
        $sheet->mergeCells('A15:J15');
        $sheet->fromArray([
            ['CLAVE UR', null, null, 'NOMBRE'],
            [(int) ($budget['responsible_unit_code'] ?? 2330), null, null, $budget['responsible_unit_name'] ?? 'Dirección de Instituciones Formadoras de Docentes'],
            ['CLAVE', null, null, 'NOMBRE'],
            [$this->programComponentKey($budget), null, null, $budget['component_name'] ?? null],
            ['ESPECIFICACIONES TÉCNICAS'],
            [
                'Actividad', 'Partida', 'Cantidad', 'Unidad', 'Descripción', 'Región', 'Nombre de la región',
                'Precio unitario', 'Importe', 'Mes presupuestado',
            ],
        ], null, 'A11');

        $rows = $this->rows($snapshot);
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A17');
        }

        $lastRow = max(17, 16 + count($rows));
        $sheet->getStyle('A2:J9')->getFont()->setBold(true);
        $sheet->getStyle('A2:J9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A6:J6')->getFont()->setSize(14);
        $sheet->getStyle('A8:J9')->getFont()->setSize(12);
        $sheet->getStyle('A11:J'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A11:J16')->getFont()->setBold(true);
        $sheet->getStyle('A11:J16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A15:J15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
        $sheet->getStyle('A16:J16')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
        $sheet->getStyle('A17:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A17:D'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F17:J'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H17:I'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (['A' => 16, 'B' => 14, 'C' => 12, 'D' => 18, 'E' => 50, 'F' => 14, 'G' => 28, 'H' => 16, 'I' => 16, 'J' => 22] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getRowDimension(8)->setRowHeight(34);
        $sheet->getRowDimension(16)->setRowHeight(32);
        foreach (range(17, $lastRow) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(34);
        }
        $sheet->freezePane('A17');
        $sheet->setAutoFilter('A16:J'.$lastRow);

        $path = tempnam(sys_get_temp_dir(), 'own-revenue-technical-sheet');
        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new \RuntimeException('No fue posible generar la Ficha técnica.');
    }

    /** @param array<string, mixed> $snapshot @return list<list<int|float|string>> */
    private function rows(array $snapshot): array
    {
        return collect($snapshot['technical_needs'] ?? [])->map(fn (array $row): array => [
            $row['activity'] ?? '',
            (int) ($row['item'] ?? 0),
            (float) ($row['quantity'] ?? 0),
            $row['unit'] ?? '',
            $row['description'] ?? '',
            '02-001',
            'Felipe Carrillo Puerto',
            ((int) ($row['unit_price_cents'] ?? 0)) / 100,
            ((int) ($row['amount_cents'] ?? 0)) / 100,
            $this->monthLabel((int) ($row['month'] ?? 0)),
        ])->all();
    }

    /** @param array<string, mixed> $budget */
    private function programComponentKey(array $budget): string
    {
        return ($budget['budget_program_code'] ?? '').($budget['component_code'] ?? '').'00000';
    }

    private function monthLabel(int $month): string
    {
        $names = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL', 5 => 'MAYO', 6 => 'JUNIO',
            7 => 'JULIO', 8 => 'AGOSTO', 9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        return isset($names[$month]) ? str_pad((string) $month, 2, '0', STR_PAD_LEFT).' - '.$names[$month] : '';
    }
}
