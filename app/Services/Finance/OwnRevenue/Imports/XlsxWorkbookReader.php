<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\XlsxCell;
use App\Data\Finance\OwnRevenue\Imports\XlsxRow;
use App\Data\Finance\OwnRevenue\Imports\XlsxSheet;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxWorkbookReader
{
    public function read(string $path): XlsxWorkbook
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        try {
            $workbook = $this->requiredXml($zip, 'xl/workbook.xml');
            $relationships = $this->workbookRelationships($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheets = [];

            foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $sheet) {
                $name = (string) $sheet['name'];
                $relationshipAttributes = $sheet->attributes(
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                );
                $relationshipId = (string) ($relationshipAttributes['id'] ?? '');
                $target = $relationships[$relationshipId] ?? null;

                if ($name === '' || $target === null) {
                    throw new RuntimeException('El libro XLSX contiene una hoja sin relación válida.');
                }

                $sheetXml = $this->requiredXml($zip, $this->workbookTargetPath($target));
                $sheets[$name] = new XlsxSheet($name, $this->rows($sheetXml, $sharedStrings));
            }

            if ($sheets === []) {
                throw new RuntimeException('El libro XLSX no contiene hojas legibles.');
            }

            return new XlsxWorkbook($sheets);
        } finally {
            $zip->close();
        }
    }

    /** @return array<string, string> */
    private function workbookRelationships(ZipArchive $zip): array
    {
        $xml = $this->requiredXml($zip, 'xl/_rels/workbook.xml.rels');
        $relationships = [];

        foreach ($xml->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            $id = (string) $relationship['Id'];
            $target = (string) $relationship['Target'];

            if ($id !== '' && $target !== '') {
                $relationships[$id] = $target;
            }
        }

        return $relationships;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $xml = $this->xml($contents, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $strings[] = $this->textContent($item);
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<int, XlsxRow>
     */
    private function rows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $rows = [];
        $previousRowNumber = 0;

        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $rowNumber = (int) $row['r'];
            $rowNumber = $rowNumber > 0 ? $rowNumber : $previousRowNumber + 1;
            $previousRowNumber = $rowNumber;
            $cells = [];

            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $coordinate = strtoupper((string) $cell['r']);

                if (! preg_match('/^([A-Z]+)[0-9]+$/', $coordinate, $match)) {
                    continue;
                }

                $column = $match[1];
                $cells[$column] = new XlsxCell(
                    coordinate: $coordinate,
                    value: $this->cellValue($cell, $sharedStrings),
                    formula: $this->optionalChildText($cell, 'f'),
                );
            }

            $rows[$rowNumber] = new XlsxRow($rowNumber, $cells);
        }

        return $rows;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return $this->textContent($cell);
        }

        $value = $this->optionalChildText($cell, 'v');

        if ($value === null) {
            return null;
        }

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function requiredXml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw new RuntimeException("El archivo XLSX no contiene {$name}.");
        }

        return $this->xml($contents, $name);
    }

    private function xml(string $contents, string $name): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

            if ($xml === false) {
                throw new RuntimeException("El XML {$name} del libro no es válido.");
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function workbookTargetPath(string $target): string
    {
        $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function optionalChildText(SimpleXMLElement $element, string $name): ?string
    {
        $children = $element->xpath('./*[local-name()="'.$name.'"]') ?: [];

        return $children === [] ? null : trim((string) $children[0]);
    }

    private function textContent(SimpleXMLElement $element): string
    {
        $texts = $element->xpath('.//*[local-name()="t"]') ?: [];

        return implode('', array_map(
            fn (SimpleXMLElement $text): string => (string) $text,
            $texts,
        ));
    }
}
