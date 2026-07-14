<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\ImportIssueData;
use App\Data\Finance\OwnRevenue\Imports\SupportingFormatAnalysis;
use App\Data\Finance\OwnRevenue\Imports\SupportingFormatLineData;
use App\Data\Finance\OwnRevenue\Imports\XlsxRow;
use App\Data\Finance\OwnRevenue\Imports\XlsxSheet;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use Illuminate\Support\Str;
use RuntimeException;

class SupportingWorkbookParser
{
    private const REGION_CODE = '02-001';

    private const REGION_NAME = 'Felipe Carrillo Puerto';

    /** @param array<string, int> $cogMap */
    public function parse(
        XlsxWorkbook $workbook,
        OwnRevenueImportFormat $format,
        array $cogMap,
    ): SupportingFormatAnalysis {
        $profile = $this->profile($format);
        [$sheet, $headerRow, $headers] = $this->findHeader($workbook, $profile['headers']);
        $issues = [];
        $lines = [];
        $sourceRows = [];

        foreach ($sheet->rows() as $rowNumber => $row) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            [$values, $source] = $this->valuesForHeaders($row, $headers);
            if (trim($values[$profile['identity']] ?? '') === '') {
                continue;
            }
            if (in_array($format, [OwnRevenueImportFormat::Fuel, OwnRevenueImportFormat::TravelExpenses], true)
                && trim($values['commission_date_label'] ?? '') === ''
                && trim($values['month'] ?? '') === '') {
                continue;
            }

            $normalized = $this->normalizeRow($format, $values);
            $rowIssues = $this->validateRow($format, $normalized, $cogMap, $sheet, $rowNumber);
            $issues = [...$issues, ...$rowIssues];
            $sourceRows[] = [
                'sheet_name' => $sheet->name,
                'row_number' => $rowNumber,
                'row_kind' => $format->value.'_line',
                'source_payload' => $source,
                'normalized_payload' => $normalized,
            ];

            if (! collect($rowIssues)->contains(
                fn (ImportIssueData $issue): bool => $issue->severity === OwnRevenueImportIssueSeverity::Error
                    && ! in_array($issue->code, ['cog.missing_item'], true),
            )) {
                $lines[] = new SupportingFormatLineData(
                    $format->value,
                    $sheet->name,
                    $rowNumber,
                    $normalized,
                );
            }
        }

        if ($sourceRows === []) {
            throw new RuntimeException('No se encontraron renglones con información en el formato seleccionado.');
        }

        return new SupportingFormatAnalysis($lines, $issues, $sourceRows);
    }

    /** @return array{identity:string,headers:array<string, list<string>>} */
    private function profile(OwnRevenueImportFormat $format): array
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => [
                'identity' => 'specific_item_code',
                'headers' => [
                    'specific_item_code' => ['partida'],
                    'sequence' => ['#'],
                    'quantity' => ['cantidad'],
                    'unit' => ['unidad'],
                    'description' => ['descripcion'],
                    'source_region_code' => ['ragion', 'region'],
                    'source_region_name' => ['nombre de la region', 'nombre region'],
                    'amount' => ['costo'],
                    'budget_month' => ['mes presupuestado'],
                ],
            ],
            OwnRevenueImportFormat::Fuel => [
                'identity' => 'reason',
                'headers' => [
                    'commission_date_label' => ['fechas de la comision'],
                    'month' => ['mes'],
                    'reason' => ['motivo de la comision'],
                    'vehicle_model' => ['modelo de vehiculo'],
                    'kilometers_per_liter' => ['rendimiento de litro de gasolina por kilometro'],
                    'outbound_origin' => ['lugar donde inicia el recorrido'],
                    'outbound_destination' => ['lugar donde finaliza el recorrido'],
                    'outbound_kilometers' => ['kilometraje'],
                    'return_origin' => ['lugar donde inicia el recorrido'],
                    'return_destination' => ['lugar donde finaliza el recorrido'],
                    'return_kilometers' => ['kilometraje2'],
                    'liters' => ['recorrido'],
                    'fuel_price' => ['costo de combustible x litro'],
                    'amount' => ['importe'],
                ],
            ],
            OwnRevenueImportFormat::TravelExpenses => [
                'identity' => 'reason',
                'headers' => [
                    'commission_date_label' => ['fechas de la comision'],
                    'month' => ['mes'],
                    'reason' => ['motivo de la comision'],
                    'person_name' => ['nombre de personal comisionado'],
                    'position' => ['cargo'],
                    'commission_days' => ['dias de comision'],
                    'destination' => ['lugar de la comision'],
                    'per_diem_uma' => ['viaticos'],
                    'lodging_uma' => ['hospedaje'],
                    'uma_value' => ['costo uma'],
                    'per_diem_amount' => ['viaticos2'],
                    'lodging_amount' => ['hospedaje3'],
                    'total_amount' => ['total'],
                    'flight_amount' => ['avion'],
                ],
            ],
            default => throw new RuntimeException('El formato no corresponde a un archivo complementario.'),
        };
    }

    /**
     * @param  array<string, list<string>>  $definitions
     * @return array{XlsxSheet, int, array<string, string>}
     */
    private function findHeader(XlsxWorkbook $workbook, array $definitions): array
    {
        foreach ($workbook->sheets() as $sheet) {
            foreach ($sheet->rows() as $row) {
                $normalizedColumns = [];
                foreach ($row->cells() as $column => $cell) {
                    $header = $this->normalize($cell->value ?? '');
                    if ($header !== '') {
                        $normalizedColumns[$header][] = $column;
                    }
                }

                $headers = [];
                foreach ($definitions as $field => $aliases) {
                    foreach ($aliases as $alias) {
                        $columns = $normalizedColumns[$alias] ?? [];
                        $column = array_shift($columns);
                        if ($column !== null) {
                            $headers[$field] = $column;
                            $normalizedColumns[$alias] = $columns;
                            break;
                        }
                    }
                }

                if (array_diff(array_keys($definitions), array_keys($headers)) === []) {
                    return [$sheet, $row->number, $headers];
                }
            }
        }

        throw new RuntimeException('No se encontraron los encabezados esperados del formato seleccionado.');
    }

    /** @return array{array<string, string>, array<string, array{coordinate:string,value:?string,formula:?string}>} */
    private function valuesForHeaders(XlsxRow $row, array $headers): array
    {
        $values = [];
        $source = [];
        foreach ($headers as $field => $column) {
            $cell = $row->cells()[$column] ?? null;
            $values[$field] = trim((string) ($cell?->value ?? ''));
            $source[$field] = [
                'coordinate' => $cell?->coordinate ?? $column.$row->number,
                'value' => $cell?->value,
                'formula' => $cell?->formula,
            ];
        }

        return [$values, $source];
    }

    /** @param array<string, string> $values @return array<string, int|string|null> */
    private function normalizeRow(OwnRevenueImportFormat $format, array $values): array
    {
        return match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => [
                'specificItemCode' => $values['specific_item_code'],
                'sequence' => $values['sequence'] ?: null,
                'quantity' => $this->decimal($values['quantity']),
                'unit' => $values['unit'],
                'description' => $values['description'],
                'sourceRegionCode' => $values['source_region_code'],
                'sourceRegionName' => $values['source_region_name'],
                'regionCode' => self::REGION_CODE,
                'regionName' => self::REGION_NAME,
                'amountCents' => $this->pesosToCents($values['amount']),
                'budgetMonth' => $this->month($values['budget_month']),
            ],
            OwnRevenueImportFormat::Fuel => [
                'commissionDateLabel' => $values['commission_date_label'],
                'month' => $this->month($values['month']) ?? $this->month($values['commission_date_label']),
                'reason' => $values['reason'],
                'vehicleModel' => $values['vehicle_model'],
                'kilometersPerLiter' => $this->decimal($values['kilometers_per_liter']),
                'outboundOrigin' => $values['outbound_origin'],
                'outboundDestination' => $values['outbound_destination'],
                'outboundKilometers' => $this->decimal($values['outbound_kilometers']),
                'returnOrigin' => $values['return_origin'],
                'returnDestination' => $values['return_destination'],
                'returnKilometers' => $this->decimal($values['return_kilometers']),
                'liters' => $this->decimal($values['liters']),
                'fuelPrice' => $this->decimal($values['fuel_price']),
                'amountCents' => $this->pesosToCents($values['amount']),
            ],
            OwnRevenueImportFormat::TravelExpenses => [
                'commissionDateLabel' => $values['commission_date_label'],
                'month' => $this->month($values['month']) ?? $this->month($values['commission_date_label']),
                'reason' => $values['reason'],
                'personName' => $values['person_name'],
                'position' => $values['position'],
                'commissionDays' => $this->decimal($values['commission_days']),
                'destination' => $values['destination'],
                'perDiemUma' => $this->decimal($values['per_diem_uma']),
                'lodgingUma' => $this->decimal($values['lodging_uma']),
                'umaValue' => $this->decimal($values['uma_value']),
                'perDiemAmountCents' => $this->pesosToCents($values['per_diem_amount']),
                'lodgingAmountCents' => $this->pesosToCents($values['lodging_amount']),
                'totalAmountCents' => $this->pesosToCents($values['total_amount']),
                'flightAmountCents' => $values['flight_amount'] === '' ? '0' : $this->pesosToCents($values['flight_amount']),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, int|string|null>  $values
     * @param  array<string, int>  $cogMap
     * @return list<ImportIssueData>
     */
    private function validateRow(
        OwnRevenueImportFormat $format,
        array $values,
        array $cogMap,
        XlsxSheet $sheet,
        int $rowNumber,
    ): array {
        $issues = [];

        if ($format === OwnRevenueImportFormat::TechnicalSheet) {
            $itemCode = (string) $values['specificItemCode'];
            if (! preg_match('/^\d{5}$/', $itemCode) || ! array_key_exists($itemCode, $cogMap)) {
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
            if ($values['sourceRegionCode'] !== self::REGION_CODE
                || $this->normalize((string) $values['sourceRegionName']) !== $this->normalize(self::REGION_NAME)) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Warning,
                    'region.normalized',
                    'region_code',
                    'La región fue normalizada a 02-001 Felipe Carrillo Puerto.',
                    [
                        'source_region' => (string) $values['sourceRegionCode'],
                        'normalized_region' => self::REGION_CODE,
                    ],
                    $sheet->name,
                    $rowNumber,
                );
            }
        }

        $requiredNumericFields = match ($format) {
            OwnRevenueImportFormat::TechnicalSheet => ['quantity', 'amountCents', 'budgetMonth'],
            OwnRevenueImportFormat::Fuel => ['month', 'kilometersPerLiter', 'outboundKilometers', 'returnKilometers', 'liters', 'fuelPrice', 'amountCents'],
            OwnRevenueImportFormat::TravelExpenses => ['month', 'commissionDays', 'perDiemUma', 'lodgingUma', 'umaValue', 'perDiemAmountCents', 'lodgingAmountCents', 'totalAmountCents', 'flightAmountCents'],
            default => [],
        };
        foreach ($requiredNumericFields as $field) {
            if (($values[$field] ?? null) === null) {
                $issues[] = new ImportIssueData(
                    OwnRevenueImportIssueSeverity::Error,
                    'value.invalid',
                    $field,
                    'El renglón contiene un valor numérico o mes no válido.',
                    [],
                    $sheet->name,
                    $rowNumber,
                );
            }
        }

        return $issues;
    }

    private function pesosToCents(string $value): ?string
    {
        $value = str_replace([',', '$', ' '], '', trim($value));
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return null;
        }

        $cents = ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0') ?: '0';

        return (new PortableIntegerAmount)->isValid($cents) ? $cents : null;
    }

    private function decimal(string $value): ?string
    {
        $value = str_replace([',', ' '], '', trim($value));
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, null);
        $whole = ltrim($whole, '0') ?: '0';

        return $fraction === null ? $whole : $whole.'.'.$fraction;
    }

    private function month(string $value): ?int
    {
        $normalized = $this->normalize($value);
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];

        if (isset($months[$normalized])) {
            return $months[$normalized];
        }

        return preg_match('/^(?:[1-9]|1[0-2])$/', $normalized) ? (int) $normalized : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9#]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
