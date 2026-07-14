<?php

namespace App\Services\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300Program;
use App\Services\Finance\MoneyToWords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class U300TechnicalSheetDocxExporter
{
    private const PageWidth = 12240;

    private const MarginTop = 1134;

    private const MarginRight = 567;

    private const MarginBottom = 1134;

    private const MarginLeft = 1701;

    private const TableWidth = self::PageWidth - self::MarginLeft - self::MarginRight;

    private const LabelColumnWidth = 2400;

    private const ValueColumnWidth = self::TableWidth - self::LabelColumnWidth;

    /**
     * @var array<int, array{relationship_id: string, name: string, path: string, extension: string, width: int|null, height: int|null}>
     */
    private array $media = [];

    private int $nextRelationshipId = 3;

    private int $fiscalYear;

    public function __construct(
        private readonly MoneyToWords $moneyToWords,
    ) {}

    public function export(U300Program $program): string
    {
        $this->fiscalYear = $program->fiscal_year;
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
        $this->media = [];
        $this->nextRelationshipId = 3;
        $path = tempnam(sys_get_temp_dir(), 'u300-technical-sheets');

        if ($path === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal de fichas técnicas.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible preparar el documento de fichas técnicas.');
        }

        $documentXml = $this->documentXml($lines);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationshipsXml());
        $zip->addFromString('word/header1.xml', $this->headerXml());
        $zip->addFromString('word/styles.xml', $this->stylesXml());

        foreach ($this->media as $media) {
            $contents = file_get_contents($media['path']);

            if ($contents !== false) {
                $zip->addFromString('word/media/'.$media['name'], $contents);
            }
        }

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
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<w:body>'
            .$body
            .'<w:sectPr><w:headerReference w:type="default" r:id="rId1"/><w:pgSz w:w="'.self::PageWidth.'" w:h="15840"/><w:pgMar w:top="'.self::MarginTop.'" w:right="'.self::MarginRight.'" w:bottom="'.self::MarginBottom.'" w:left="'.self::MarginLeft.'" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>'
            .'</w:body>'
            .'</w:document>';
    }

    private function technicalSheetBlock(U300BudgetLine $line): string
    {
        return $this->technicalSheetTable($line);
    }

    private function headerXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .$this->sheetHeader()
            .'</w:hdr>';
    }

    private function sheetHeader(): string
    {
        return $this->paragraph('CENTRO REGIONAL DE EDUCACIÓN NORMAL', [
            'alignment' => 'right',
            'bold' => true,
            'fontSize' => 22,
            'color' => '6B7280',
        ])
            .$this->paragraph('Felipe Carrillo Puerto, Quintana Roo', [
                'alignment' => 'right',
                'fontSize' => 18,
                'color' => '6B7280',
            ])
            .$this->paragraph('EDINEN 2025', [
                'alignment' => 'right',
                'bold' => true,
                'fontSize' => 20,
                'color' => '5B87A5',
            ])
            .$this->paragraph('FICHA TÉCNICA', [
                'alignment' => 'right',
                'bold' => true,
                'fontSize' => 24,
                'color' => '5B87A5',
                'after' => 120,
            ]);
    }

    private function technicalSheetTable(U300BudgetLine $line): string
    {
        $classification = $line->expenseClassification;
        $sheet = $line->technicalSheet;
        $cogCode = trim($classification?->specific_item_code ?? '');
        $cogName = trim($classification?->specific_item_name ?? '');
        $cog = trim($cogCode.' '.$cogName);
        $itemName = trim($sheet?->item_name ?? '') !== ''
            ? $sheet?->item_name
            : ($line->description ?? '');

        $technicalSpecs = $sheet?->technical_specs ?? '';
        $goods = $sheet?->goods_profile !== null
            ? $this->structuredGoods($sheet->goods_profile)
            : $this->parseGoods($technicalSpecs);

        $rows = [
            ['label' => 'ACCIÓN', 'content' => trim($line->action->number.' '.$line->action->name), 'header' => true],
            ['label' => $this->cogLabel($cogCode, $cogName, $cog), 'label_xml' => true, 'content' => $itemName],
            ['label' => 'Monto asignado', 'content' => $this->formatMoney($line->amount_cents)],
            ['label' => 'Objetivo', 'content' => $sheet?->objective ?? ''],
            ['label' => 'Trabajos a realizar:', 'content' => $sheet?->work_description ?? ''],
            [
                'label' => 'Perfil / especificaciones técnicas',
                'content' => $goods === [] ? $technicalSpecs : $this->goodsTables($goods),
                'xml' => $goods !== [],
            ],
            ['label' => '# de beneficiarios', 'content' => $this->richTextBlock($sheet?->beneficiaries ?? ''), 'xml' => true],
            ['label' => 'Fecha', 'content' => $this->formatScheduledDate($sheet?->scheduled_date ?? $line->exercise_month ?? '')],
            ['label' => 'Entregables', 'content' => $this->richTextBlock($sheet?->deliverables ?? ''), 'xml' => true],
            ['label' => 'Lugar de entrega', 'content' => $sheet?->delivery_location ?? ''],
            ['label' => 'Responsable de la supervisión de la entrega', 'content' => $sheet?->supervisor ?? ''],
            ['label' => 'Condiciones y forma de pago', 'content' => $sheet?->payment_terms ?? ''],
        ];

        return '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="'.self::TableWidth.'" w:type="dxa"/><w:jc w:val="center"/><w:tblLayout w:type="autofit"/><w:tblCellMar><w:top w:w="55" w:type="dxa"/><w:left w:w="70" w:type="dxa"/><w:bottom w:w="55" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar><w:tblBorders><w:top w:val="single" w:sz="6" w:color="000000"/><w:left w:val="single" w:sz="6" w:color="000000"/><w:bottom w:val="single" w:sz="6" w:color="000000"/><w:right w:val="single" w:sz="6" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/></w:tblBorders></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="'.self::LabelColumnWidth.'"/><w:gridCol w:w="'.self::ValueColumnWidth.'"/></w:tblGrid>'
            .collect($rows)->map(fn (array $row): string => $this->tableRow(
                $row['label'],
                $row['content'],
                $row['header'] ?? false,
                $row['xml'] ?? false,
                $row['label_xml'] ?? false,
            ))->implode('')
            .'</w:tbl>';
    }

    private function tableRow(string $label, string $value, bool $header = false, bool $valueIsXml = false, bool $labelIsXml = false): string
    {
        return '<w:tr>'
            .$this->tableCell($label, self::LabelColumnWidth, true, $header, $labelIsXml)
            .$this->tableCell($value, self::ValueColumnWidth, $header, $header, $valueIsXml)
            .'</w:tr>';
    }

    private function tableCell(string $text, int $width, bool $bold = false, bool $header = false, bool $contentIsXml = false): string
    {
        $fillXml = $header ? '<w:shd w:fill="0E5A7A"/>' : '';
        $color = $header ? 'FFFFFF' : '000000';
        $fontSize = $header ? 26 : 22;
        $content = $contentIsXml
            ? $this->cellXmlContent($text)
            : $this->paragraph($text, [
                'bold' => $bold,
                'fontSize' => $fontSize,
                'color' => $color,
            ]);

        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$fillXml.'<w:vAlign w:val="center"/></w:tcPr>'
            .$content
            .'</w:tc>';
    }

    private function cogLabel(string $code, string $name, string $fallback): string
    {
        if ($code === '' || $name === '') {
            return $this->paragraph($fallback, [
                'bold' => true,
                'fontSize' => 22,
            ]);
        }

        return '<w:p><w:pPr><w:jc w:val="left"/><w:spacing w:before="0" w:after="0" w:line="220" w:lineRule="auto"/></w:pPr>'
            .$this->run($code, 22, bold: true)
            .'<w:r><w:rPr><w:rFonts w:ascii="Aptos Narrow" w:hAnsi="Aptos Narrow" w:cs="Aptos Narrow"/><w:color w:val="000000"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:br/><w:t xml:space="preserve">'.$this->escape($name).'</w:t></w:r>'
            .'</w:p>';
    }

    private function cellXmlContent(string $content): string
    {
        return str_ends_with($content, '</w:tbl>')
            ? $content.$this->hiddenCellTerminatorParagraph()
            : $content;
    }

    /**
     * @param  array<int, array{description: string, unit: string, quantity: string, specifications: string, photo: string}>  $goods
     */
    private function goodsTables(array $goods): string
    {
        $content = $this->goodsSummaryTable($goods);

        foreach ($goods as $good) {
            if (trim($good['specifications']) === '') {
                continue;
            }

            $content .= $this->specificationsTable($good);
        }

        return $content;
    }

    /**
     * @param  array<int, array{description: string, unit: string, quantity: string, specifications: string, photo: string}>  $goods
     */
    private function goodsSummaryTable(array $goods): string
    {
        $header = ['UNIDAD'.PHP_EOL.'DE MEDIDA', 'DESCRIPCIÓN DEL BIEN', 'CANTIDAD'.PHP_EOL.'MÍNIMA'];

        return '<w:tbl>'
            .$this->autoFitTableProperties()
            .'<w:tr>'
            .collect($header)->map(fn (string $text, int $index): string => $this->nestedTableCell(
                $this->paragraph($text, ['alignment' => 'center', 'bold' => true, 'fontSize' => 14]),
                0,
                contentIsXml: true,
                autoWidth: true,
            ))->implode('')
            .'</w:tr>'
            .collect($goods)->map(fn (array $good): string => '<w:tr>'
                .$this->nestedTableCell($this->paragraph($good['unit'], ['fontSize' => 17]), 0, contentIsXml: true, autoWidth: true)
                .$this->nestedTableCell($this->paragraph($good['description'], ['fontSize' => 17]), 0, contentIsXml: true, autoWidth: true)
                .$this->nestedTableCell($this->paragraph($good['quantity'], ['alignment' => 'center', 'fontSize' => 17]), 0, contentIsXml: true, autoWidth: true)
                .'</w:tr>')->implode('')
            .'</w:tbl>';
    }

    /**
     * @param  array{description: string, unit: string, quantity: string, specifications: string, photo: string}  $good
     */
    private function specificationsTable(array $good): string
    {
        [$title, $body] = $this->splitSpecificationsTitle($good['specifications']);
        $photoXml = $this->imageParagraph($good['photo']);
        $hasPhoto = $photoXml !== '';
        $columns = $hasPhoto ? [4672, 2900] : [7572];

        $headerRow = '<w:tr>'
            .$this->nestedTableCell(
                $this->paragraph($title, ['bold' => true, 'fontSize' => 17]),
                self::ValueColumnWidth,
                count($columns),
                true,
            )
            .'</w:tr>';

        $labelsRow = '<w:tr>'
            .$this->nestedTableCell($this->paragraph('Especificaciones', ['alignment' => 'center', 'bold' => true, 'fontSize' => 14]), $columns[0], contentIsXml: true);

        if ($hasPhoto) {
            $labelsRow .= $this->nestedTableCell($this->paragraph('Foto de referencia', ['alignment' => 'center', 'bold' => true, 'fontSize' => 14]), $columns[1], contentIsXml: true);
        }

        $labelsRow .= '</w:tr>';

        $bodyRow = '<w:tr>'
            .$this->nestedTableCell($this->richTextBlock($body, 17), $columns[0], contentIsXml: true);

        if ($hasPhoto) {
            $bodyRow .= $this->nestedTableCell($photoXml, $columns[1], contentIsXml: true);
        }

        $bodyRow .= '</w:tr>';

        return '<w:p/>'
            .'<w:tbl>'
            .$this->nestedTableProperties(self::ValueColumnWidth)
            .$this->nestedTableGrid($columns)
            .$headerRow
            .$labelsRow
            .$bodyRow
            .'</w:tbl>';
    }

    private function nestedTableProperties(int $width): string
    {
        return '<w:tblPr><w:tblW w:w="'.$width.'" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="45" w:type="dxa"/><w:left w:w="55" w:type="dxa"/><w:bottom w:w="45" w:type="dxa"/><w:right w:w="55" w:type="dxa"/></w:tblCellMar><w:tblBorders><w:top w:val="single" w:sz="4" w:color="000000"/><w:left w:val="single" w:sz="4" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:color="000000"/><w:right w:val="single" w:sz="4" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/></w:tblBorders></w:tblPr>';
    }

    private function autoFitTableProperties(): string
    {
        return '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="autofit"/><w:tblCellMar><w:top w:w="45" w:type="dxa"/><w:left w:w="55" w:type="dxa"/><w:bottom w:w="45" w:type="dxa"/><w:right w:w="55" w:type="dxa"/></w:tblCellMar><w:tblBorders><w:top w:val="single" w:sz="4" w:color="000000"/><w:left w:val="single" w:sz="4" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:color="000000"/><w:right w:val="single" w:sz="4" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/></w:tblBorders></w:tblPr>';
    }

    /**
     * @param  array<int, int>  $columns
     */
    private function nestedTableGrid(array $columns): string
    {
        return '<w:tblGrid>'
            .collect($columns)->map(fn (int $width): string => '<w:gridCol w:w="'.$width.'"/>')->implode('')
            .'</w:tblGrid>';
    }

    private function nestedTableCell(string $content, int $width, int $gridSpan = 1, bool $contentIsXml = false, bool $autoWidth = false): string
    {
        $gridSpanXml = $gridSpan > 1 ? '<w:gridSpan w:val="'.$gridSpan.'"/>' : '';
        $widthXml = $autoWidth
            ? '<w:tcW w:w="0" w:type="auto"/>'
            : '<w:tcW w:w="'.$width.'" w:type="dxa"/>';
        $cellContent = $contentIsXml
            ? $content
            : $this->paragraph($content);

        return '<w:tc><w:tcPr>'.$widthXml.$gridSpanXml.'<w:vAlign w:val="top"/></w:tcPr>'
            .$cellContent
            .'</w:tc>';
    }

    private function richTextBlock(string $text, int $fontSize = 22): string
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        if ($lines === []) {
            return $this->emptyParagraph();
        }

        return collect($lines)
            ->map(function (string $line) use ($fontSize): string {
                $trimmedLine = trim($line);
                $isBullet = str_starts_with($trimmedLine, '-');
                $content = $isBullet ? trim(substr($trimmedLine, 1)) : $trimmedLine;

                return $this->richParagraph(($isBullet ? '• ' : '').$content, $fontSize);
            })
            ->implode('');
    }

    private function richParagraph(string $text, int $fontSize): string
    {
        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="220" w:lineRule="auto"/></w:pPr>'
            .$this->richRuns($text, $fontSize)
            .'</w:p>';
    }

    private function richRuns(string $text, int $fontSize): string
    {
        $runs = '';
        $remaining = $text;

        while (preg_match('/(\*[^*]+\*|_[^_]+_)/u', $remaining, $match, PREG_OFFSET_CAPTURE) === 1) {
            $token = $match[0][0];
            $offset = $match[0][1];

            if ($offset > 0) {
                $runs .= $this->run(substr($remaining, 0, $offset), $fontSize);
            }

            $isBold = str_starts_with($token, '*');
            $runs .= $this->run(substr($token, 1, -1), $fontSize, bold: $isBold, italic: ! $isBold);
            $remaining = substr($remaining, $offset + strlen($token));
        }

        if ($remaining !== '') {
            $runs .= $this->run($remaining, $fontSize);
        }

        return $runs;
    }

    private function run(string $text, int $fontSize, bool $bold = false, bool $italic = false): string
    {
        $boldXml = $bold ? '<w:b/>' : '';
        $italicXml = $italic ? '<w:i/>' : '';

        return '<w:r><w:rPr><w:rFonts w:ascii="Aptos Narrow" w:hAnsi="Aptos Narrow" w:cs="Aptos Narrow"/>'.$boldXml.$italicXml.'<w:color w:val="000000"/><w:sz w:val="'.$fontSize.'"/><w:szCs w:val="'.$fontSize.'"/></w:rPr><w:t xml:space="preserve">'.$this->escape($text).'</w:t></w:r>';
    }

    private function emptyParagraph(): string
    {
        return '<w:p/>';
    }

    private function hiddenCellTerminatorParagraph(): string
    {
        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="1" w:lineRule="exact"/><w:rPr><w:vanish/><w:sz w:val="1"/><w:szCs w:val="1"/></w:rPr></w:pPr></w:p>';
    }

    /**
     * @param  array{alignment?: string, bold?: bool, fontSize?: int, color?: string, after?: int}  $options
     */
    private function paragraph(string $text, array $options = []): string
    {
        $alignment = $options['alignment'] ?? 'left';
        $boldXml = ($options['bold'] ?? false) ? '<w:b/>' : '';
        $fontSize = (string) ($options['fontSize'] ?? 22);
        $color = $options['color'] ?? '000000';
        $after = (string) ($options['after'] ?? 0);
        $lines = preg_split('/\R/u', $text) ?: [''];

        $runs = collect($lines)
            ->map(function (string $line, int $index) use ($boldXml, $fontSize, $color): string {
                $break = $index > 0 ? '<w:br/>' : '';

                return '<w:r><w:rPr><w:rFonts w:ascii="Aptos Narrow" w:hAnsi="Aptos Narrow" w:cs="Aptos Narrow"/>'.$boldXml.'<w:color w:val="'.$color.'"/><w:sz w:val="'.$fontSize.'"/><w:szCs w:val="'.$fontSize.'"/></w:rPr>'.$break.'<w:t xml:space="preserve">'.$this->escape($line).'</w:t></w:r>';
            })
            ->implode('');

        return '<w:p><w:pPr><w:jc w:val="'.$alignment.'"/><w:spacing w:before="0" w:after="'.$after.'" w:line="220" w:lineRule="auto"/></w:pPr>'.$runs.'</w:p>';
    }

    /**
     * @return array<int, array{description: string, unit: string, quantity: string, specifications: string, photo: string}>
     */
    private function parseGoods(string $technicalSpecs): array
    {
        $blocks = preg_split('/\R{2,}/u', trim($technicalSpecs)) ?: [];
        $goods = [];

        foreach ($blocks as $block) {
            $lines = preg_split('/\R/u', trim($block)) ?: [];

            if ($lines === []) {
                continue;
            }

            $description = trim((string) preg_replace('/^\d+\.\s*/u', '', array_shift($lines)));
            $unit = '';
            $quantity = '';
            $specifications = '';
            $photo = '';
            $collectingSpecifications = false;

            foreach ($lines as $line) {
                $trimmedLine = trim($line);

                if (Str::startsWith($trimmedLine, 'Unidad de medida:')) {
                    $unit = trim(Str::after($trimmedLine, 'Unidad de medida:'));
                    $collectingSpecifications = false;

                    continue;
                }

                if (Str::startsWith($trimmedLine, 'Cantidad mínima:')) {
                    $quantity = trim(Str::after($trimmedLine, 'Cantidad mínima:'));
                    $collectingSpecifications = false;

                    continue;
                }

                if (Str::startsWith($trimmedLine, 'Especificaciones:')) {
                    $specifications = trim(Str::after($trimmedLine, 'Especificaciones:'));
                    $collectingSpecifications = true;

                    continue;
                }

                if (Str::startsWith($trimmedLine, 'Foto de referencia:')) {
                    $photo = trim(Str::after($trimmedLine, 'Foto de referencia:'));
                    $collectingSpecifications = false;

                    continue;
                }

                if ($collectingSpecifications) {
                    $specifications .= "\n".$trimmedLine;
                }
            }

            if ($description === '' || ($unit === '' && $quantity === '' && $specifications === '' && $photo === '')) {
                continue;
            }

            $goods[] = [
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'specifications' => trim($specifications),
                'photo' => $photo,
            ];
        }

        return $goods;
    }

    /**
     * @param  list<array<string, mixed>>  $goodsProfile
     * @return list<array{description: string, unit: string, quantity: string, specifications: string, photo: string}>
     */
    private function structuredGoods(array $goodsProfile): array
    {
        return collect($goodsProfile)
            ->map(fn (array $good): array => [
                'description' => (string) ($good['description'] ?? ''),
                'unit' => (string) ($good['unit'] ?? ''),
                'quantity' => (string) ($good['minimum_quantity'] ?? ''),
                'specifications' => (string) ($good['specifications'] ?? ''),
                'photo' => (string) ($good['reference_photo_path'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitSpecificationsTitle(string $specifications): array
    {
        if (preg_match('/\*([^*]+)\*/u', $specifications, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return ['Especificaciones', trim($specifications)];
        }

        $title = $matches[1][0];
        $marker = $matches[0][0];
        $offset = $matches[0][1];
        $body = trim(substr($specifications, 0, $offset).substr($specifications, $offset + strlen($marker)));

        return [$title, $body];
    }

    private function imageParagraph(string $reference): string
    {
        $media = $this->registerImage($reference);

        if ($media === null) {
            return '';
        }

        [$cx, $cy] = $this->imageDimensions($media);

        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:docPr id="'.count($this->media).'" name="Foto de referencia"/>'
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="Foto de referencia"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$media['relationship_id'].'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic>'
            .'</wp:inline>'
            .'</w:drawing></w:r></w:p>';
    }

    /**
     * @return array{relationship_id: string, name: string, path: string, extension: string, width: int|null, height: int|null}|null
     */
    private function registerImage(string $reference): ?array
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $path = $this->resolveImagePath($reference);

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! in_array($extension, ['jpg', 'png'], true)) {
            return null;
        }

        $relationshipId = 'rId'.$this->nextRelationshipId++;
        $name = 'image'.(count($this->media) + 1).'.'.$extension;
        $imageSize = getimagesize($path);
        $media = [
            'relationship_id' => $relationshipId,
            'name' => $name,
            'path' => $path,
            'extension' => $extension,
            'width' => is_array($imageSize) ? (int) ($imageSize[0] ?? 0) : null,
            'height' => is_array($imageSize) ? (int) ($imageSize[1] ?? 0) : null,
        ];

        $this->media[] = $media;

        return $media;
    }

    /**
     * @param  array{relationship_id: string, name: string, path: string, extension: string, width: int|null, height: int|null}  $media
     * @return array{0: int, 1: int}
     */
    private function imageDimensions(array $media): array
    {
        $width = $media['width'] ?? null;
        $height = $media['height'] ?? null;
        $cx = 1620000;

        if ($width === null || $height === null || $width <= 0 || $height <= 0) {
            return [$cx, 1215000];
        }

        return [$cx, (int) round($cx * ($height / $width))];
    }

    private function resolveImagePath(string $reference): ?string
    {
        $relativePath = match (true) {
            str_starts_with($reference, 'storage/') => Str::after($reference, 'storage/'),
            str_starts_with($reference, '/storage/') => Str::after($reference, '/storage/'),
            str_starts_with($reference, 'u300/') => $reference,
            default => null,
        };

        if ($relativePath === null || preg_match(
            '/\Au300\/technical-sheets\/reference-photos\/[A-Za-z0-9._-]+\z/',
            $relativePath,
        ) !== 1) {
            return null;
        }

        return Storage::disk('public')->path($relativePath);
    }

    private function formatScheduledDate(string $scheduledDate): string
    {
        $months = [
            'ENE' => 'Enero',
            'FEB' => 'Febrero',
            'MAR' => 'Marzo',
            'ABR' => 'Abril',
            'MAY' => 'Mayo',
            'JUN' => 'Junio',
            'JUL' => 'Julio',
            'AGO' => 'Agosto',
            'SEP' => 'Septiembre',
            'OCT' => 'Octubre',
            'NOV' => 'Noviembre',
            'DIC' => 'Diciembre',
        ];

        $normalizedDate = Str::of($scheduledDate)->trim()->upper()->toString();

        return isset($months[$normalizedDate])
            ? $months[$normalizedDate].' de '.$this->fiscalYear
            : $scheduledDate;
    }

    private function formatMoney(int $amountCents): string
    {
        $amount = $amountCents / 100;
        $wholePesos = intdiv($amountCents, 100);
        $amountInWords = Str::of($this->moneyToWords->convert($wholePesos))
            ->lower()
            ->replace('m.n.', 'M.N.');

        return '$'.number_format($amount, 2, '.', ',').' (Son: '.$amountInWords.')';
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
            .'<Default Extension="jpg" ContentType="image/jpeg"/>'
            .'<Default Extension="jpeg" ContentType="image/jpeg"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationshipsXml(): string
    {
        $imageRelationships = collect($this->media)
            ->map(fn (array $media): string => '<Relationship Id="'.$media['relationship_id'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$media['name'].'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .$imageRelationships
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults>'
            .'<w:rPrDefault><w:rPr><w:rFonts w:ascii="Aptos Narrow" w:hAnsi="Aptos Narrow" w:cs="Aptos Narrow"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:before="0" w:after="0" w:line="220" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            .'</w:docDefaults>'
            .'</w:styles>';
    }
}
