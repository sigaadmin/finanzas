<?php

namespace App\Services\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class U300TechnicalSheetReportWorkbookExporter
{
    public function export(U300Program $program): string
    {
        $adjustedVersion = $program->budgetVersions()
            ->where('kind', 'adjusted')
            ->first();
        $lines = $adjustedVersion?->budgetLines()
            ->where('amount_cents', '>', 0)
            ->with(['action', 'expenseClassification', 'technicalSheet'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get() ?? collect();
        $rows = $lines
            ->flatMap(fn (U300BudgetLine $line): array => $this->rowsFor($line))
            ->values()
            ->all();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fichas técnicas');
        $sheet->fromArray([
            [
                'cvAcción', 'Acción', 'Monto asignado', 'cvPartida', 'Partida',
                'Cantidad', 'Unidad de medida', 'Descripción', 'Precio unitario', 'Total',
            ],
        ], null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');

            foreach ($rows as $index => $row) {
                $sheet->setCellValueExplicit('A'.($index + 2), (string) $row[0], DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.($index + 2), (string) $row[3], DataType::TYPE_STRING);
            }
        }

        $lastRow = max(2, count($rows) + 1);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle('A1:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C2:C'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F2:F'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('I2:J'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J'.$lastRow);

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'u300-technical-sheets-report');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal del reporte de fichas técnicas.');
        }

        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new RuntimeException('No fue posible generar el reporte de fichas técnicas.');
    }

    /**
     * @return list<list<float|int|string>>
     */
    private function rowsFor(U300BudgetLine $line): array
    {
        $chapterCode = $line->expenseClassification?->chapter_code;

        if (in_array($chapterCode, ['2000', '5000'], true)) {
            return collect($line->technicalSheet?->goods_profile ?? [])
                ->filter(fn (array $good): bool => collect($good)
                    ->only(['unit', 'description', 'minimum_quantity', 'unit_price', 'specifications'])
                    ->contains(fn (mixed $value): bool => filled($value)))
                ->map(fn (array $good): array => $this->row(
                    $line,
                    (float) ($good['minimum_quantity'] ?? 0),
                    (string) ($good['unit'] ?? ''),
                    (string) ($good['description'] ?? ''),
                    (float) ($good['unit_price'] ?? 0),
                ))
                ->all();
        }

        if (! in_array($chapterCode, ['3000', '6000'], true) || $line->technicalSheet === null) {
            return [];
        }

        $amount = $line->amount_cents / 100;

        return [$this->row(
            $line,
            1,
            $chapterCode === '6000' ? 'Obra' : 'Servicio',
            $this->serviceDescription((string) $line->expenseClassification?->specific_item_name),
            $amount,
        )];
    }

    /**
     * @return list<float|int|string>
     */
    private function row(
        U300BudgetLine $line,
        float|int $quantity,
        string $unit,
        string $description,
        float $unitPrice,
    ): array {
        return [
            $line->action?->number ?? '',
            $line->action?->name ?? '',
            $line->amount_cents / 100,
            $line->expenseClassification?->specific_item_code ?? '',
            $line->expenseClassification?->specific_item_name ?? '',
            $quantity,
            $unit,
            $description,
            $unitPrice,
            $quantity * $unitPrice,
        ];
    }

    private function serviceDescription(string $itemName): string
    {
        return match ($itemName) {
            'Instalación, reparación y mantenimiento de maquinaria, otros equipos y herramientas agropecuarias e industriales y equipos especializados' => 'Instalación y mantenimiento de equipos especializados',
            'Otros arrendamientos' => 'Renta de equipo',
            'Servicios de capacitación' => 'Curso-taller',
            'Servicios de elaboración e impresión de documentos' => 'Impresión de materiales',
            'Pasajes aéreos nacionales', 'Transportación por atención a terceros' => 'Boleto de avión',
            'Viáticos en el país', 'Alimentación por atención a terceros' => 'Alimentación',
            'Hospedaje en el país', 'Hospedaje por atención a terceros' => 'Hospedaje',
            'Obra de edificaciones de uso no habitacional' => 'Remodelación',
            default => $itemName,
        };
    }
}
