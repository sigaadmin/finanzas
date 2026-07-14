<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\ImportIssueData;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetAnalysis;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetLineData;
use App\Data\Finance\OwnRevenue\Imports\XlsxCell;
use App\Data\Finance\OwnRevenue\Imports\XlsxRow;
use App\Data\Finance\OwnRevenue\Imports\XlsxSheet;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use Illuminate\Support\Str;
use RuntimeException;

class WorkSheetWorkbookParser
{
    private const MONTHS = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const REGION_CODE = '02-001';

    private const REGION_NAME = 'Felipe Carrillo Puerto';

    /**
     * @param  array<string, int>  $activityMap
     * @param  array<string, int>  $cogMap
     */
    public function parse(XlsxWorkbook $workbook, array $activityMap, array $cogMap): WorkSheetAnalysis
    {
        [$sheet, $lastHeaderRow, $headers] = $this->findHeader($workbook);
        $issues = [];
        $sourceRows = [];
        $groups = [];
        $currentActivity = null;

        foreach ($sheet->rows() as $rowNumber => $row) {
            if ($rowNumber <= $lastHeaderRow) {
                continue;
            }

            [$values, $source] = $this->valuesForHeaders($row, $headers);
            $specificItemCode = trim($values['partida'] ?? '');

            if (! preg_match('/^\d{5}$/', $specificItemCode)) {
                continue;
            }

            $activityLabel = trim($values['actividad'] ?? '');
            if ($activityLabel !== '') {
                $currentActivity = $this->parseActivity($activityLabel);
            }

            $normalizedValues = $values;
            $normalizedValues['actividad'] = $currentActivity === null
                ? null
                : $currentActivity['code'].' - '.$currentActivity['name'];
            $normalizedValues['region'] = self::REGION_CODE;
            $normalizedValues['nombre region'] = self::REGION_NAME;

            $sourceRows[] = [
                'sheet_name' => $sheet->name,
                'row_number' => $rowNumber,
                'row_kind' => 'work_sheet_line',
                'source_payload' => $source,
                'normalized_payload' => $normalizedValues,
            ];

            if ($currentActivity === null) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'activity.missing',
                    'activity_code',
                    'No fue posible identificar la actividad de esta fila.',
                    ['activity' => $activityLabel],
                    $sheet->name,
                    $rowNumber,
                );

                continue;
            }

            if (! array_key_exists($currentActivity['code'], $activityMap)) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'activity.missing',
                    'activity_code',
                    'La actividad no existe en el presupuesto seleccionado.',
                    ['activity_code' => $currentActivity['code']],
                    $sheet->name,
                    $rowNumber,
                );
            }

            if (! array_key_exists($specificItemCode, $cogMap)) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'cog.missing_item',
                    'specific_item_code',
                    'La partida no existe en el COG del ejercicio.',
                    ['specific_item_code' => $specificItemCode],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $sourceRegionCode = trim($values['region'] ?? '');
            $sourceRegionName = trim($values['nombre region'] ?? '');
            if ($sourceRegionCode !== self::REGION_CODE || $sourceRegionName !== self::REGION_NAME) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'region.normalized',
                    'region_code',
                    'La región fue normalizada a 02-001 Felipe Carrillo Puerto.',
                    [
                        'source_region' => $sourceRegionCode,
                        'source_region_name' => $sourceRegionName,
                        'normalized_region' => self::REGION_CODE,
                    ],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $months = [];
            $invalidAmount = false;
            foreach (self::MONTHS as $month => $header) {
                $amountCents = $this->pesosToCents($values[$header] ?? null, blankAsZero: true);
                if ($amountCents === null) {
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
                $months[$month] = $amountCents;
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
                    'work_sheet.annual_mismatch',
                    'annual',
                    'El anual informado no coincide con la suma mensual; se usará la suma mensual.',
                    ['source_cents' => $sourceAnnual, 'calculated_cents' => $calculatedAnnual],
                    $sheet->name,
                    $rowNumber,
                );
            }

            $key = $currentActivity['code'].'|'.$specificItemCode.'|'.self::REGION_CODE;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'activity_code' => $currentActivity['code'],
                    'activity_name' => $currentActivity['name'],
                    'item_name' => trim($values['insumo'] ?? ''),
                    'item_names' => [],
                    'specific_item_code' => $specificItemCode,
                    'source_regions' => [],
                    'months' => array_fill(1, 12, '0'),
                    'source_rows' => [],
                ];
            }

            $itemName = trim($values['insumo'] ?? '');
            if ($itemName !== '' && ! in_array($itemName, $groups[$key]['item_names'], true)) {
                $groups[$key]['item_names'][] = $itemName;
            }
            $sourceRegion = ['code' => $sourceRegionCode, 'name' => $sourceRegionName];
            if (! in_array($sourceRegion, $groups[$key]['source_regions'], true)) {
                $groups[$key]['source_regions'][] = $sourceRegion;
            }
            foreach ($months as $month => $amountCents) {
                $groups[$key]['months'][$month] = $this->add($groups[$key]['months'][$month], $amountCents);
            }
            $groups[$key]['source_rows'][] = $rowNumber;
        }

        foreach ($groups as $group) {
            if (count($group['source_rows']) > 1) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'work_sheet.duplicate_group',
                    null,
                    'Se agruparon varias filas con la misma actividad y partida.',
                    [
                        'activity_code' => $group['activity_code'],
                        'specific_item_code' => $group['specific_item_code'],
                        'source_rows' => $group['source_rows'],
                    ],
                    $sheet->name,
                );
            }

            if (count($group['item_names']) > 1) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'work_sheet.item_name_mismatch',
                    'item_name',
                    'Las filas agrupadas usan nombres de insumo distintos.',
                    [
                        'activity_code' => $group['activity_code'],
                        'specific_item_code' => $group['specific_item_code'],
                        'item_names' => $group['item_names'],
                        'source_rows' => $group['source_rows'],
                    ],
                    $sheet->name,
                );
            }
        }

        $lines = array_map(fn (array $group): WorkSheetLineData => new WorkSheetLineData(
            activityCode: $group['activity_code'],
            activityName: $group['activity_name'],
            itemName: $group['item_name'],
            specificItemCode: $group['specific_item_code'],
            regionCode: self::REGION_CODE,
            regionName: self::REGION_NAME,
            sourceRegions: $group['source_regions'],
            months: $group['months'],
            annualAmountCents: $this->sum($group['months']),
            sourceRows: $group['source_rows'],
        ), array_values($groups));

        return new WorkSheetAnalysis($lines, $issues, $sourceRows);
    }

    /** @return array{XlsxSheet, int, array<string, string>} */
    private function findHeader(XlsxWorkbook $workbook): array
    {
        $required = ['actividad', 'insumo', 'partida', 'region', 'nombre region', 'presupuesto', 'anual', ...array_values(self::MONTHS)];

        foreach ($workbook->sheets() as $sheet) {
            $rows = $sheet->rows();
            foreach ($rows as $rowNumber => $upperRow) {
                $lowerRow = $rows[$rowNumber + 1] ?? null;
                if ($lowerRow === null) {
                    continue;
                }

                $headers = $this->combinedHeaders($upperRow, $lowerRow);
                if (array_diff($required, array_keys($headers)) === []) {
                    return [$sheet, $lowerRow->number, $headers];
                }
            }
        }

        throw new RuntimeException('No se encontraron los encabezados esperados de la Hoja de trabajo.');
    }

    /** @return array<string, string> */
    private function combinedHeaders(XlsxRow $upperRow, XlsxRow $lowerRow): array
    {
        $columns = array_unique([...array_keys($upperRow->cells()), ...array_keys($lowerRow->cells())]);
        $headers = [];

        foreach ($columns as $column) {
            $upper = $upperRow->cells()[$column]->value ?? '';
            $lower = $lowerRow->cells()[$column]->value ?? '';
            $field = $this->headerField($lower) ?? $this->headerField($upper);
            if ($field !== null) {
                $headers[$field] = $column;
            }
        }

        return $headers;
    }

    private function headerField(string $value): ?string
    {
        return match ($this->normalize($value)) {
            'actividades unidad de presupuestacion', 'actividad unidad de presupuestacion', 'actividad' => 'actividad',
            'insumos', 'insumo' => 'insumo',
            'partida' => 'partida',
            'region', 'clave region' => 'region',
            'nombre de la region', 'nombre region' => 'nombre region',
            'presupuesto' => 'presupuesto',
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
            'septiembre', 'octubre', 'noviembre', 'diciembre', 'anual' => $this->normalize($value),
            default => null,
        };
    }

    /** @return array{array<string, ?string>, array<string, array{coordinate:string,value:?string,formula:?string}>} */
    private function valuesForHeaders(XlsxRow $row, array $headers): array
    {
        $values = [];
        $source = [];
        foreach ($headers as $header => $column) {
            $cell = $row->cells()[$column] ?? new XlsxCell($column.$row->number, null, null);
            $values[$header] = $cell->value;
            $source[$header] = [
                'coordinate' => $cell->coordinate,
                'value' => $cell->value,
                'formula' => $cell->formula,
            ];
        }

        return [$values, $source];
    }

    /** @return array{code:string,name:string}|null */
    private function parseActivity(string $value): ?array
    {
        if (! preg_match('/^([A-Z0-9]+(?:-[A-Z0-9]+)+)\s*(?:-\s*(.+))?$/iu', trim($value), $matches)) {
            return null;
        }

        $code = strtoupper($matches[1]);

        return ['code' => $code, 'name' => trim($matches[2] ?? '')];
    }

    private function pesosToCents(?string $value, bool $blankAsZero = false): ?string
    {
        if ($value === null || trim($value) === '') {
            return $blankAsZero ? '0' : null;
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

    private function normalize(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '') ?? '');
    }
}
