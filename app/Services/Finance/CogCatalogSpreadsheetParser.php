<?php

namespace App\Services\Finance;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class CogCatalogSpreadsheetParser
{
    /**
     * @return list<array{
     *     chapter_code: string,
     *     chapter_name: string,
     *     concept_code: string,
     *     concept_name: string,
     *     generic_item_code: string,
     *     generic_item_name: string,
     *     specific_item_code: string,
     *     specific_item_name: string,
     *     expense_type_code: string,
     *     expense_type_name: string
     * }>
     */
    public function parse(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX del catálogo COG.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('El archivo XLSX no contiene la hoja esperada.');
        }

        $rows = $this->rowsFromSheet($sheetXml, $sharedStrings);
        $header = array_shift($rows) ?? [];
        $indexes = $this->headerIndexes($header);

        return collect($rows)
            ->map(fn (array $row): array => [
                'chapter_code' => $row[$indexes['Cve Capítulo']] ?? '',
                'chapter_name' => $row[$indexes['Capítulo']] ?? '',
                'concept_code' => $row[$indexes['Cve Concepto']] ?? '',
                'concept_name' => $row[$indexes['Concepto']] ?? '',
                'generic_item_code' => $row[$indexes['Cve Partida Genérica']] ?? '',
                'generic_item_name' => $row[$indexes['Partida Genérica']] ?? '',
                'specific_item_code' => $row[$indexes['Cve Partida Específica']] ?? '',
                'specific_item_name' => $row[$indexes['Partida Específica']] ?? '',
                'expense_type_code' => $row[$indexes['Cve Tipo de Gasto']] ?? '',
                'expense_type_name' => $row[$indexes['Tipo de Gasto']] ?? '',
            ])
            ->filter(fn (array $row): bool => $row['specific_item_code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = new SimpleXMLElement($xml);

        return collect($strings->xpath('//*[local-name()="si"]') ?: [])
            ->map(fn (SimpleXMLElement $item): string => $this->textContent($item))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<list<string>>
     */
    private function rowsFromSheet(string $sheetXml, array $sharedStrings): array
    {
        $sheet = new SimpleXMLElement($sheetXml);
        $rows = [];

        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $cells = [];

            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $cells[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            ksort($cells);
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $sharedStrings[(int) $this->childText($cell, 'v')] ?? '';
        }

        if ($type === 'inlineStr') {
            return $this->textContent($cell);
        }

        return $this->childText($cell, 'v');
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', $reference, $match);
        $letters = $match[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerIndexes(array $header): array
    {
        return collect($header)
            ->mapWithKeys(fn (string $value, int $index): array => [$value => $index])
            ->all();
    }

    private function childText(SimpleXMLElement $element, string $name): string
    {
        $children = $element->xpath('./*[local-name()="'.$name.'"]') ?: [];

        if ($children === []) {
            return '';
        }

        return trim((string) $children[0]);
    }

    private function textContent(SimpleXMLElement $element): string
    {
        $texts = $element->xpath('.//*[local-name()="t"]') ?: [];

        if ($texts === []) {
            return trim((string) $element);
        }

        return trim(collect($texts)->map(fn (SimpleXMLElement $text): string => (string) $text)->implode(''));
    }
}
