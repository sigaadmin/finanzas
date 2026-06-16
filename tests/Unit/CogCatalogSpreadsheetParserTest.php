<?php

use App\Services\Finance\CogCatalogSpreadsheetParser;

function createMinimalCogXlsx(string $path): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
      <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
      <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    </Types>
    XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    </Relationships>
    XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
      <sheets><sheet name="COG" sheetId="1" r:id="rId1"/></sheets>
    </workbook>
    XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
      <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    </Relationships>
    XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
      <sheetData>
        <row r="1">
          <c r="A1" t="inlineStr"><is><t>Cve Capítulo</t></is></c>
          <c r="B1" t="inlineStr"><is><t>Capítulo</t></is></c>
          <c r="C1" t="inlineStr"><is><t>Cve Concepto</t></is></c>
          <c r="D1" t="inlineStr"><is><t>Concepto</t></is></c>
          <c r="E1" t="inlineStr"><is><t>Cve Partida Genérica</t></is></c>
          <c r="F1" t="inlineStr"><is><t>Partida Genérica</t></is></c>
          <c r="G1" t="inlineStr"><is><t>Cve Partida Específica</t></is></c>
          <c r="H1" t="inlineStr"><is><t>Partida Específica</t></is></c>
          <c r="I1" t="inlineStr"><is><t>Cve Tipo de Gasto</t></is></c>
          <c r="J1" t="inlineStr"><is><t>Tipo de Gasto</t></is></c>
        </row>
        <row r="2">
          <c r="A2" t="inlineStr"><is><t>3000</t></is></c>
          <c r="B2" t="inlineStr"><is><t>Servicios Generales</t></is></c>
          <c r="C2" t="inlineStr"><is><t>3700</t></is></c>
          <c r="D2" t="inlineStr"><is><t>Servicios de traslado y viáticos</t></is></c>
          <c r="E2" t="inlineStr"><is><t>3750</t></is></c>
          <c r="F2" t="inlineStr"><is><t>Viáticos en el país</t></is></c>
          <c r="G2" t="inlineStr"><is><t>37501</t></is></c>
          <c r="H2" t="inlineStr"><is><t>Viáticos en el país</t></is></c>
          <c r="I2" t="inlineStr"><is><t>1</t></is></c>
          <c r="J2" t="inlineStr"><is><t>Gasto corriente</t></is></c>
        </row>
      </sheetData>
    </worksheet>
    XML);
    $zip->close();
}

function createPrefixedCogXlsx(string $path): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
      <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
      <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    </Types>
    XML);
    $zip->addFromString('xl/workbook.xml', '<x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheets><x:sheet name="COG" sheetId="1"/></x:sheets></x:workbook>');
    $zip->addFromString('xl/sharedStrings.xml', '<x:sst xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" />');
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
    <?xml version="1.0" encoding="utf-8"?>
    <x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
      <x:sheetData>
        <x:row r="1">
          <x:c r="A1" t="str"><x:v>Cve Capítulo</x:v></x:c>
          <x:c r="B1" t="str"><x:v>Capítulo</x:v></x:c>
          <x:c r="C1" t="str"><x:v>Cve Concepto</x:v></x:c>
          <x:c r="D1" t="str"><x:v>Concepto</x:v></x:c>
          <x:c r="E1" t="str"><x:v>Cve Partida Genérica</x:v></x:c>
          <x:c r="F1" t="str"><x:v>Partida Genérica</x:v></x:c>
          <x:c r="G1" t="str"><x:v>Cve Partida Específica</x:v></x:c>
          <x:c r="H1" t="str"><x:v>Partida Específica</x:v></x:c>
          <x:c r="I1" t="str"><x:v>Cve Tipo de Gasto</x:v></x:c>
          <x:c r="J1" t="str"><x:v>Tipo de Gasto</x:v></x:c>
        </x:row>
        <x:row r="2">
          <x:c r="A2" t="str"><x:v>2000</x:v></x:c>
          <x:c r="B2" t="str"><x:v>Materiales y Suministros</x:v></x:c>
          <x:c r="C2" t="str"><x:v>2100</x:v></x:c>
          <x:c r="D2" t="str"><x:v>Materiales de administración</x:v></x:c>
          <x:c r="E2" t="str"><x:v>2110</x:v></x:c>
          <x:c r="F2" t="str"><x:v>Materiales, útiles y equipos menores de oficina</x:v></x:c>
          <x:c r="G2" t="str"><x:v>21101</x:v></x:c>
          <x:c r="H2" t="str"><x:v>Materiales y útiles de oficina</x:v></x:c>
          <x:c r="I2" t="str"><x:v>1</x:v></x:c>
          <x:c r="J2" t="str"><x:v>Gasto corriente</x:v></x:c>
        </x:row>
      </x:sheetData>
    </x:worksheet>
    XML);
    $zip->close();
}

test('it parses the COG classification spreadsheet rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'cog').'.xlsx';
    createMinimalCogXlsx($path);

    $rows = (new CogCatalogSpreadsheetParser)->parse($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'chapter_code' => '3000',
            'chapter_name' => 'Servicios Generales',
            'concept_code' => '3700',
            'generic_item_code' => '3750',
            'specific_item_code' => '37501',
            'specific_item_name' => 'Viáticos en el país',
            'expense_type_code' => '1',
            'expense_type_name' => 'Gasto corriente',
        ]);
});

test('it parses generated spreadsheets with prefixed worksheet nodes', function () {
    $path = tempnam(sys_get_temp_dir(), 'cog-prefixed').'.xlsx';
    createPrefixedCogXlsx($path);

    $rows = (new CogCatalogSpreadsheetParser)->parse($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'chapter_code' => '2000',
            'chapter_name' => 'Materiales y Suministros',
            'specific_item_code' => '21101',
            'specific_item_name' => 'Materiales y útiles de oficina',
        ]);
});
