<?php

namespace App\Services\Finance\U300;

use RuntimeException;
use ZipArchive;

class U300FinancialWorkbookExporter
{
    /**
     * @param  array<string, mixed>  $dashboard
     */
    public function export(array $dashboard): string
    {
        $path = tempnam(sys_get_temp_dir(), 'u300-financial-workbook');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal del libro financiero.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible preparar el libro financiero.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($dashboard));
        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer el libro financiero.');
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     */
    private function worksheetXml(array $dashboard): string
    {
        $rows = [
            [1, ['Información financiera U300']],
            [2, ['Proyecto', $dashboard['name']]],
            [3, ['Ejercicio fiscal', $dashboard['fiscal_year']]],
            [5, ['Acción', 'COG', 'Mes', 'Monto adecuado', 'Ejercido', 'Disponible', 'Estado']],
        ];

        $rowNumber = 6;
        foreach ($dashboard['lines'] as $line) {
            $rows[] = [
                $rowNumber,
                [
                    trim($line['action_number'].' '.$line['action_name']),
                    trim(($line['cog_code'] ?? 'Sin COG').' '.($line['cog_name'] ?? '')),
                    $line['exercise_month'] ?? '',
                    $line['amount_cents'] / 100,
                    $line['executed_cents'] / 100,
                    $line['available_cents'] / 100,
                    $line['status'],
                ],
            ];
            $rowNumber++;
        }

        $sheetData = collect($rows)
            ->map(fn (array $row): string => $this->rowXml($row[0], $row[1]))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<cols>'
            .'<col min="1" max="1" width="34" customWidth="1"/>'
            .'<col min="2" max="2" width="32" customWidth="1"/>'
            .'<col min="3" max="3" width="12" customWidth="1"/>'
            .'<col min="4" max="6" width="16" customWidth="1"/>'
            .'<col min="7" max="7" width="24" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>'.$sheetData.'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  list<mixed>  $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $cells[] = $this->cellXml($this->columnName($index + 1).$rowNumber, $value);
        }

        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function cellXml(string $reference, mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.number_format((float) $value, 2, '.', '').'</v></c>';
        }

        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
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
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Resumen" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
