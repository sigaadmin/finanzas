<?php

namespace App\Services\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\MoneyToWords;
use RuntimeException;
use ZipArchive;

class U300TechnicalSheetDocxExporter
{
    public function __construct(
        private readonly MoneyToWords $moneyToWords,
    ) {}

    public function export(U300Program $program): string
    {
        $program->loadMissing(
            'budgetVersions.budgetLines.action',
            'budgetVersions.budgetLines.expenseClassification',
            'budgetVersions.budgetLines.technicalSheet',
        );

        $adjustedVersion = $program->budgetVersions->firstWhere('kind', 'adjusted');

        $lines = $adjustedVersion?->budgetLines
            ->filter(fn (U300BudgetLine $line): bool => $line->technicalSheet !== null)
            ->sortBy('sort_order')
            ->values() ?? collect();

        return $this->buildDocument($lines);
    }

    private function buildDocument(iterable $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'u300-technical-sheets');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de fichas técnicas.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible preparar el documento de fichas técnicas.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('word/document.xml', $this->documentXml($lines));
        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer el documento de fichas técnicas.');
        }

        return $contents;
    }

    private function documentXml(iterable $lines): string
    {
        $tables = [];

        foreach ($lines as $line) {
            $tables[] = $this->technicalSheetBlock($line);
        }

        $body = implode('<w:p><w:r><w:br w:type="page"/></w:r></w:p>', $tables);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>'
            .'</w:body>'
            .'</w:document>';
    }

    private function technicalSheetBlock(U300BudgetLine $line): string
    {
        return $this->sheetHeader().$this->technicalSheetTable($line);
    }

    private function sheetHeader(): string
    {
        return $this->paragraph('CENTRO REGIONAL DE EDUCACIÓN NORMAL', [
            'alignment' => 'right',
            'bold' => true,
            'fontSize' => 14,
            'color' => '6B7280',
        ])
            .$this->paragraph('Felipe Carrillo Puerto, Quintana Roo', [
                'alignment' => 'right',
                'fontSize' => 11,
                'color' => '6B7280',
            ])
            .$this->paragraph('EDINEN 2025', [
                'alignment' => 'right',
                'bold' => true,
                'fontSize' => 12,
                'color' => '5B87A5',
            ])
            .$this->paragraph('FICHA TÉCNICA', [
                'alignment' => 'right',
                'bold' => true,
                'fontSize' => 16,
                'color' => '5B87A5',
                'after' => 120,
            ]);
    }

    private function technicalSheetTable(U300BudgetLine $line): string
    {
        $classification = $line->expenseClassification;
        $sheet = $line->technicalSheet;
        $cog = trim(($classification?->specific_item_code ?? '').' '.($classification?->specific_item_name ?? ''));
        $itemName = trim($sheet?->item_name ?? '') !== ''
            ? $sheet?->item_name
            : ($line->description ?? '');

        $rows = [
            ['ACCIÓN', trim($line->action->number.' '.$line->action->name), true],
            [$cog, $itemName, false],
            ['Monto asignado', $this->formatMoney($line->amount_cents), false],
            ['Objetivo', $sheet?->objective ?? '', false],
            ['Trabajos a realizar:', $sheet?->work_description ?? '', false],
            ['Perfil / especificaciones técnicas', $sheet?->technical_specs ?? '', false],
            ['# de beneficiarios', $sheet?->beneficiaries ?? '', false],
            ['Fecha', $sheet?->scheduled_date ?? $line->exercise_month ?? '', false],
            ['Entregables', $sheet?->deliverables ?? '', false],
            ['Lugar de entrega', $sheet?->delivery_location ?? '', false],
            ['Responsable de la supervisión de la entrega', $sheet?->supervisor ?? '', false],
            ['Condiciones y forma de pago', $sheet?->payment_terms ?? '', false],
        ];

        return '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="7200" w:type="dxa"/><w:jc w:val="center"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="55" w:type="dxa"/><w:left w:w="70" w:type="dxa"/><w:bottom w:w="55" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar><w:tblBorders><w:top w:val="single" w:sz="6" w:color="000000"/><w:left w:val="single" w:sz="6" w:color="000000"/><w:bottom w:val="single" w:sz="6" w:color="000000"/><w:right w:val="single" w:sz="6" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/></w:tblBorders></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="1800"/><w:gridCol w:w="5400"/></w:tblGrid>'
            .collect($rows)->map(fn (array $row): string => $this->tableRow($row[0], $row[1], $row[2]))->implode('')
            .'</w:tbl>';
    }

    private function tableRow(string $label, string $value, bool $header = false): string
    {
        return '<w:tr>'
            .$this->tableCell($label, 1800, true, $header)
            .$this->tableCell($value, 5400, $header, $header)
            .'</w:tr>';
    }

    private function tableCell(string $text, int $width, bool $bold = false, bool $header = false): string
    {
        $fillXml = $header ? '<w:shd w:fill="0E5A7A"/>' : '';
        $color = $header ? 'FFFFFF' : '000000';
        $fontSize = $header ? 16 : 15;

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$fillXml.'<w:vAlign w:val="center"/></w:tcPr>'
            .$this->paragraph($text, [
                'bold' => $bold,
                'fontSize' => $fontSize,
                'color' => $color,
            ])
            .'</w:tc>';
    }

    /**
     * @param  array{alignment?: string, bold?: bool, fontSize?: int, color?: string, after?: int}  $options
     */
    private function paragraph(string $text, array $options = []): string
    {
        $alignment = $options['alignment'] ?? 'left';
        $boldXml = ($options['bold'] ?? false) ? '<w:b/>' : '';
        $fontSize = (string) ($options['fontSize'] ?? 15);
        $color = $options['color'] ?? '000000';
        $after = (string) ($options['after'] ?? 0);
        $lines = preg_split('/\R/u', $text) ?: [''];

        $runs = collect($lines)
            ->map(function (string $line, int $index) use ($boldXml, $fontSize, $color): string {
                $break = $index > 0 ? '<w:br/>' : '';

                return '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'.$boldXml.'<w:color w:val="'.$color.'"/><w:sz w:val="'.$fontSize.'"/><w:szCs w:val="'.$fontSize.'"/></w:rPr>'.$break.'<w:t xml:space="preserve">'.$this->escape($line).'</w:t></w:r>';
            })
            ->implode('');

        return '<w:p><w:pPr><w:jc w:val="'.$alignment.'"/><w:spacing w:before="0" w:after="'.$after.'" w:line="220" w:lineRule="auto"/></w:pPr>'.$runs.'</w:p>';
    }

    private function formatMoney(int $amountCents): string
    {
        $amount = $amountCents / 100;
        $wholePesos = intdiv($amountCents, 100);

        return '$'.number_format($amount, 2, '.', ',').' ('.$this->moneyToWords->convert($wholePesos).')';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }
}
