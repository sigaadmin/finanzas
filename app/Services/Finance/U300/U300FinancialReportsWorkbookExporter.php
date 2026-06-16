<?php

namespace App\Services\Finance\U300;

use RuntimeException;
use ZipArchive;

class U300FinancialReportsWorkbookExporter
{
    /**
     * @param  array<string, mixed>  $reports
     */
    public function export(array $reports): string
    {
        $path = tempnam(sys_get_temp_dir(), 'u300-financial-reports');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de reportes financieros.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible preparar el libro de reportes financieros.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($this->desgloseRows($reports)));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($this->concentradoRows($reports)));
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->worksheetXml($this->presupuestoRows($reports)));
        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        if ($contents === false) {
            throw new RuntimeException('No fue posible leer el libro de reportes financieros.');
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $reports
     * @return list<list<mixed>>
     */
    private function desgloseRows(array $reports): array
    {
        $rows = [['Proyecto', 'Meta', 'Acción', 'COG', 'Partida', 'Monto', 'Mes']];

        foreach ($reports['desglose'] as $row) {
            $rows[] = [
                $row['project'],
                $row['goal'],
                $row['action'],
                $row['cog_code'],
                $row['cog_name'],
                $row['amount_cents'] / 100,
                $row['month'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $reports
     * @return list<list<mixed>>
     */
    private function concentradoRows(array $reports): array
    {
        $rows = [['Descripción de la partida', 'Partida', 'Monto', 'Comprometido', 'Ejercido', 'Disponible']];

        foreach ($reports['concentrado'] as $row) {
            $rows[] = [
                $row['cog_name'],
                $row['cog_code'],
                $row['amount_cents'] / 100,
                $row['committed_cents'] / 100,
                $row['executed_cents'] / 100,
                $row['available_cents'] / 100,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $reports
     * @return list<list<mixed>>
     */
    private function presupuestoRows(array $reports): array
    {
        $months = $reports['months'];
        $rows = [array_merge(['Descripción de la partida', 'Partida'], $months, ['TOTAL'])];

        foreach ($reports['presupuesto'] as $row) {
            $rows[] = array_merge(
                [$row['cog_name'], $row['cog_code']],
                collect($months)->map(fn (string $month): float => ($row['months'][$month] ?? 0) / 100)->all(),
                [$row['total_cents'] / 100],
            );
        }

        $rows[] = array_merge(
            ['TOTAL', ''],
            collect($months)->map(fn (string $month): float => ($reports['presupuesto_totals']['months'][$month] ?? 0) / 100)->all(),
            [$reports['presupuesto_totals']['total_cents'] / 100],
        );

        return $rows;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function worksheetXml(array $rows): string
    {
        $sheetData = collect($rows)
            ->map(fn (array $row, int $index): string => $this->rowXml($index + 1, $row))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetData.'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  list<mixed>  $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        return '<row r="'.$rowNumber.'">'.collect($values)
            ->map(fn (mixed $value, int $index): string => $this->cellXml($this->columnName($index + 1).$rowNumber, $value))
            ->implode('').'</row>';
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
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
            .'<sheets>'
            .'<sheet name="DESGLOSE" sheetId="1" r:id="rId1"/>'
            .'<sheet name="CONCENTRADO" sheetId="2" r:id="rId2"/>'
            .'<sheet name="PRESUPUESTO" sheetId="3" r:id="rId3"/>'
            .'</sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'</Relationships>';
    }
}
