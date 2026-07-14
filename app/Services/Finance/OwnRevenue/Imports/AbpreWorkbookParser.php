<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\AbpreAnalysis;
use App\Data\Finance\OwnRevenue\Imports\AbpreJustificationData;
use App\Data\Finance\OwnRevenue\Imports\AbpreLineData;
use App\Data\Finance\OwnRevenue\Imports\ImportIssueData;
use App\Data\Finance\OwnRevenue\Imports\XlsxRow;
use App\Data\Finance\OwnRevenue\Imports\XlsxSheet;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use Illuminate\Support\Str;
use RuntimeException;

class AbpreWorkbookParser
{
    private const MONTHS = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * @param  array{fiscal_year:int,responsible_unit_code:string}  $budget
     * @param  array<string, int>  $cogMap
     */
    public function parse(XlsxWorkbook $workbook, array $budget, array $cogMap): AbpreAnalysis
    {
        [$sheet, $headerRow, $headers] = $this->findHeader($workbook, [
            'clave unidad responsable', 'partida', 'enero', 'diciembre', 'anual',
        ]);
        $issues = [];
        $sourceRows = [];
        $groups = [];
        $forward = [];

        $detectedYear = $this->detectedYear($workbook);
        if ($detectedYear !== null && $detectedYear !== $budget['fiscal_year']) {
            $issues[] = new ImportIssueData(
                OwnRevenueImportIssueSeverity::Warning,
                'year.mismatch',
                'fiscal_year',
                'El año detectado no coincide con el ejercicio seleccionado.',
                ['detected_year' => $detectedYear, 'fiscal_year' => $budget['fiscal_year']],
            );
        }

        foreach ($sheet->rows() as $rowNumber => $row) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            [$values, $source] = $this->valuesForHeaders($row, $headers);
            $itemCode = trim($values['partida'] ?? '');

            if ($itemCode === '') {
                continue;
            }

            foreach (array_slice(array_keys($headers), 0, 10) as $field) {
                if (($values[$field] ?? '') !== '') {
                    $forward[$field] = $values[$field];
                } elseif (isset($forward[$field])) {
                    $values[$field] = $forward[$field];
                }
            }

            $sourceRows[] = [
                'sheet_name' => $sheet->name,
                'row_number' => $rowNumber,
                'row_kind' => 'budget_line',
                'source_payload' => $source,
                'normalized_payload' => $values,
            ];

            $unitCode = trim($values['clave unidad responsable'] ?? '');
            if ($unitCode !== $budget['responsible_unit_code']) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Info,
                    'abpre.other_unit',
                    'responsible_unit_code',
                    'La fila corresponde a otra unidad responsable y no será importada.',
                    ['responsible_unit_code' => $unitCode],
                    $sheet->name,
                    $rowNumber,
                );

                continue;
            }

            if (! array_key_exists($itemCode, $cogMap)) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'cog.missing_item',
                    'specific_item_code',
                    'La partida no existe en el COG del ejercicio.',
                    ['specific_item_code' => $itemCode],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $region = trim($values['clave region'] ?? '');
            if ($region !== '02-001') {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'region.normalized',
                    'region_code',
                    'La región fue normalizada a 02-001 Felipe Carrillo Puerto.',
                    ['source_region' => $region, 'normalized_region' => '02-001'],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $months = [];
            $invalidAmount = false;
            foreach (self::MONTHS as $month => $header) {
                $cents = $this->pesosToCents($values[$header] ?? null);
                if ($cents === null) {
                    $issues[] = new ImportIssueData(
                        OwnRevenueImportIssueSeverity::Error,
                        'amount.invalid',
                        $header,
                        'El importe debe ser un número no negativo con máximo dos decimales.',
                        ['value' => $values[$header] ?? null],
                        $sheet->name,
                        $rowNumber,
                    );
                    $invalidAmount = true;
                    break;
                }
                $months[$month] = $cents;
            }

            $sourceAnnual = $this->pesosToCents($values['anual'] ?? null);
            if ($sourceAnnual === null) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'amount.invalid',
                    'anual',
                    'El importe anual no es válido.',
                    ['value' => $values['anual'] ?? null],
                    $sheet->name,
                    $rowNumber,
                );
                $invalidAmount = true;
            }

            if ($invalidAmount) {
                continue;
            }

            $calculatedAnnual = $this->sum($months);
            if ($sourceAnnual !== $calculatedAnnual) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'abpre.annual_mismatch',
                    'annual',
                    'El anual informado no coincide con la suma mensual; se usará la suma mensual.',
                    ['source_cents' => $sourceAnnual, 'calculated_cents' => $calculatedAnnual],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $groupValues = [
                'responsible_unit_code' => $unitCode,
                'responsible_unit_name' => trim($values['nombre de unidad responsable'] ?? ''),
                'budget_program_code' => trim($values['programa presupuestario'] ?? ''),
                'budget_program_name' => trim($values['nombre programa presupuestario'] ?? ''),
                'component_code' => trim($values['clave componente'] ?? ''),
                'component_name' => trim($values['nombre componente'] ?? ''),
                'official_activity_code' => trim($values['clave actividad'] ?? ''),
                'official_activity_name' => trim($values['nombre actividad'] ?? ''),
                'region_code' => '02-001',
                'region_name' => 'Felipe Carrillo Puerto',
                'specific_expense_concept_code' => trim($values['concepto especifico del gasto'] ?? '') ?: null,
                'specific_item_code' => $itemCode,
            ];
            $key = json_encode(array_values($groupValues), JSON_THROW_ON_ERROR);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    ...$groupValues,
                    'source_regions' => [],
                    'months' => array_fill(1, 12, '0'),
                    'source_rows' => [],
                ];
            }

            $sourceRegion = [
                'code' => $region,
                'name' => trim($values['nombre de la region'] ?? ''),
            ];
            if (! in_array($sourceRegion, $groups[$key]['source_regions'], true)) {
                $groups[$key]['source_regions'][] = $sourceRegion;
            }

            foreach ($months as $month => $cents) {
                $groups[$key]['months'][$month] = $this->add($groups[$key]['months'][$month], $cents);
            }
            $groups[$key]['source_rows'][] = $rowNumber;
        }

        [$justifications, $justificationRows] = $this->parseJustifications($workbook);
        $sourceRows = [...$sourceRows, ...$justificationRows];
        $justifiedItems = array_column($justifications, 'specificItemCode');

        foreach ($groups as $group) {
            if (! in_array($group['specific_item_code'], $justifiedItems, true)) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'abpre.missing_justification',
                    'justification',
                    'La partida no tiene una justificación asociada.',
                    ['specific_item_code' => $group['specific_item_code']],
                );
            }
        }

        $lines = array_map(fn (array $group): AbpreLineData => new AbpreLineData(
            responsibleUnitCode: $group['responsible_unit_code'],
            responsibleUnitName: $group['responsible_unit_name'],
            budgetProgramCode: $group['budget_program_code'],
            budgetProgramName: $group['budget_program_name'],
            componentCode: $group['component_code'],
            componentName: $group['component_name'],
            officialActivityCode: $group['official_activity_code'],
            officialActivityName: $group['official_activity_name'],
            regionCode: $group['region_code'],
            regionName: $group['region_name'],
            sourceRegions: $group['source_regions'],
            specificExpenseConceptCode: $group['specific_expense_concept_code'],
            specificItemCode: $group['specific_item_code'],
            months: $group['months'],
            annualAmountCents: $this->sum($group['months']),
            sourceRows: $group['source_rows'],
        ), array_values($groups));

        return new AbpreAnalysis($lines, array_values($justifications), $issues, $sourceRows);
    }

    /** @return array{XlsxSheet, int, array<string, string>} */
    private function findHeader(XlsxWorkbook $workbook, array $required): array
    {
        foreach ($workbook->sheets() as $sheet) {
            foreach ($sheet->rows() as $row) {
                $headers = [];
                foreach ($row->cells() as $column => $cell) {
                    $normalized = $this->normalize($cell->value ?? '');
                    if ($normalized !== '') {
                        $headers[$normalized] = $column;
                    }
                }
                if (array_diff($required, array_keys($headers)) === []) {
                    return [$sheet, $row->number, $headers];
                }
            }
        }
        throw new RuntimeException('No se encontraron los encabezados esperados del formato ABPRE.');
    }

    /** @return array{array<string, ?string>, array<string, ?string>} */
    private function valuesForHeaders(XlsxRow $row, array $headers): array
    {
        $values = [];
        $source = [];
        foreach ($headers as $header => $column) {
            $cell = $row->cells()[$column] ?? null;
            $values[$header] = $cell?->value;
            $source[$header] = $cell?->value;
        }

        return [$values, $source];
    }

    /** @return array{list<AbpreJustificationData>, list<array{sheet_name:string,row_number:int,row_kind:string,source_payload:array<string, ?string>,normalized_payload:array<string, mixed>}>} */
    private function parseJustifications(XlsxWorkbook $workbook): array
    {
        try {
            [$sheet, $headerRow, $headers] = $this->findHeader($workbook, ['unidad responble', 'partida', 'justificacion']);
        } catch (RuntimeException) {
            return [[], []];
        }
        $items = [];
        $rows = [];
        foreach ($sheet->rows() as $rowNumber => $row) {
            if ($rowNumber <= $headerRow) {
                continue;
            }
            [$values, $source] = $this->valuesForHeaders($row, $headers);
            $code = trim($values['partida'] ?? '');
            if ($code === '') {
                continue;
            }
            $items[] = new AbpreJustificationData(
                trim($values['capitulo'] ?? ''), trim($values['descripcion capitulo'] ?? ''), $code,
                trim($values['descripcion partida'] ?? ''), trim($values['programa prresupuestario'] ?? ''),
                trim($values['componente'] ?? ''), trim($values['impacto en metas'] ?? '') ?: null,
                trim($values['justificacion'] ?? ''), $rowNumber,
            );
            $rows[] = ['sheet_name' => $sheet->name, 'row_number' => $rowNumber, 'row_kind' => 'justification', 'source_payload' => $source, 'normalized_payload' => $values];
        }

        return [$items, $rows];
    }

    private function pesosToCents(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = str_replace([',', '$', ' '], '', trim($value));
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $match)) {
            return null;
        }
        $cents = ltrim($match[1].str_pad($match[2] ?? '', 2, '0'), '0') ?: '0';
        $max = '18446744073709551615';
        if (strlen($cents) > strlen($max) || (strlen($cents) === strlen($max) && strcmp($cents, $max) > 0)) {
            return null;
        }

        return $cents;
    }

    /** @param array<int, string> $values */
    private function sum(array $values): string
    {
        $sum = '0';
        foreach ($values as $value) {
            $sum = $this->add($sum, $value);
        }

        return $sum;
    }

    private function add(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $left = strrev($left);
        $right = strrev($right);
        $length = max(strlen($left), strlen($right));
        for ($index = 0; $index < $length; $index++) {
            $total = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($total % 10);
            $carry = intdiv($total, 10);
        }
        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return strrev($result);
    }

    private function detectedYear(XlsxWorkbook $workbook): ?int
    {
        $years = [];
        foreach ($workbook->sheets() as $sheet) {
            foreach ($sheet->rows() as $row) {
                foreach ($row->cells() as $cell) {
                    $value = $cell->value ?? '';

                    if (! preg_match('/\b(ejercicio|presupuesto|fiscal|ano)\b/', $this->normalize($value))) {
                        continue;
                    }

                    preg_match_all('/(?<!\d)(20\d{2})(?!\d)/', $value, $matches);
                    foreach ($matches[1] as $year) {
                        $years[(int) $year] = ($years[(int) $year] ?? 0) + 1;
                    }
                }
            }
        }
        if ($years === []) {
            return null;
        }
        arsort($years);

        return array_key_first($years);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '') ?? '');
    }
}
