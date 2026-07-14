<?php

final class OwnRevenueXlsxFixtureFactory
{
    /**
     * @param  array<string, array<int, array<string, string|array{value?: string|null, formula?: string, formula_attributes?: array<string, string>, type?: string}>>>  $sheets
     */
    public static function create(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-xlsx-').'.xlsx';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el fixture XLSX.');
        }

        $sharedStrings = self::sharedStrings($sheets);
        $zip->addFromString('[Content_Types].xml', self::contentTypes(count($sheets), $sharedStrings !== []));
        $zip->addFromString('_rels/.rels', self::packageRelationships());
        $zip->addFromString('xl/workbook.xml', self::workbookXml(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships(count($sheets)));

        if ($sharedStrings !== []) {
            $zip->addFromString('xl/sharedStrings.xml', self::sharedStringsXml(array_keys($sharedStrings)));
        }

        $sheetNumber = 1;

        foreach ($sheets as $rows) {
            $zip->addFromString(
                "xl/worksheets/sheet{$sheetNumber}.xml",
                self::worksheetXml($rows, $sharedStrings),
            );
            $sheetNumber++;
        }

        $zip->close();

        return $path;
    }

    /** @return array<string, int> */
    private static function sharedStrings(array $sheets): array
    {
        $strings = [];

        foreach ($sheets as $rows) {
            foreach ($rows as $cells) {
                foreach ($cells as $cell) {
                    if (is_array($cell) && ($cell['type'] ?? null) === 'shared') {
                        $value = (string) ($cell['value'] ?? '');
                        $strings[$value] ??= count($strings);
                    }
                }
            }
        }

        return $strings;
    }

    private static function contentTypes(int $sheetCount, bool $hasSharedStrings): string
    {
        $overrides = '';

        for ($sheet = 1; $sheet <= $sheetCount; $sheet++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$sheet.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        if ($hasSharedStrings) {
            $overrides .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.$overrides.'</Types>';
    }

    private static function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    /** @param list<string> $sheetNames */
    private static function workbookXml(array $sheetNames): string
    {
        $sheets = '';

        foreach ($sheetNames as $index => $name) {
            $number = $index + 1;
            $sheets .= '<sheet name="'.self::xml($name).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private static function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($sheet = 1; $sheet <= $sheetCount; $sheet++) {
            $relationships .= '<Relationship Id="rId'.$sheet.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheet.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    /** @param list<string> $strings */
    private static function sharedStringsXml(array $strings): string
    {
        $items = implode('', array_map(
            fn (string $value): string => '<si><t>'.self::xml($value).'</t></si>',
            $strings,
        ));

        return '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$items.'</sst>';
    }

    private static function worksheetXml(array $rows, array $sharedStrings): string
    {
        $rowXml = '';

        foreach ($rows as $rowNumber => $cells) {
            $cellXml = '';

            foreach ($cells as $column => $definition) {
                $cell = is_array($definition) ? $definition : ['value' => $definition, 'type' => 'inline'];
                $coordinate = strtoupper($column).$rowNumber;
                $type = $cell['type'] ?? 'inline';
                $value = $cell['value'] ?? null;
                $formulaAttributes = '';
                foreach ($cell['formula_attributes'] ?? [] as $attribute => $attributeValue) {
                    $formulaAttributes .= ' '.self::xml($attribute).'="'.self::xml($attributeValue).'"';
                }
                $formula = isset($cell['formula'])
                    ? '<f'.$formulaAttributes.'>'.self::xml($cell['formula']).'</f>'
                    : '';

                if ($type === 'shared') {
                    $cellXml .= '<c r="'.$coordinate.'" t="s">'.$formula.'<v>'.$sharedStrings[(string) $value].'</v></c>';
                } elseif ($type === 'inline') {
                    $cellXml .= '<c r="'.$coordinate.'" t="inlineStr">'.$formula.'<is><t>'.self::xml((string) $value).'</t></is></c>';
                } else {
                    $cachedValue = $value === null ? '' : '<v>'.self::xml((string) $value).'</v>';
                    $cellXml .= '<c r="'.$coordinate.'">'.$formula.$cachedValue.'</c>';
                }
            }

            $rowXml .= '<row r="'.$rowNumber.'">'.$cellXml.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowXml.'</sheetData></worksheet>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
