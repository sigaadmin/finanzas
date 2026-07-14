<?php

namespace App\Services\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\WorkbookDetection;
use App\Data\Finance\OwnRevenue\Imports\XlsxCell;
use App\Data\Finance\OwnRevenue\Imports\XlsxWorkbook;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use Illuminate\Support\Str;

class OwnRevenueWorkbookFormatDetector
{
    public function detect(XlsxWorkbook $workbook): WorkbookDetection
    {
        $scores = [];

        foreach ($this->signatures() as $format => $requiredHeaders) {
            $bestMatches = [];

            foreach ($workbook->sheets() as $sheet) {
                $rows = $sheet->rows();

                foreach ($rows as $rowNumber => $row) {
                    $followingRow = $rows[$rowNumber + 1] ?? null;
                    $headers = collect(array_merge(
                        array_values($row->cells()),
                        array_values($followingRow?->cells() ?? []),
                    ))
                        ->map(fn (XlsxCell $cell): string => $this->canonicalHeader($cell->value ?? ''))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $matches = array_values(array_intersect($requiredHeaders, $headers));

                    if (count($matches) > count($bestMatches)) {
                        $bestMatches = $matches;
                    }
                }
            }

            $scores[$format] = [
                'confidence' => (int) floor((count($bestMatches) / count($requiredHeaders)) * 100),
                'matches' => $bestMatches,
            ];
        }

        uasort($scores, fn (array $left, array $right): int => $right['confidence'] <=> $left['confidence']);
        $formats = array_keys($scores);
        $bestFormat = $formats[0];
        $best = $scores[$bestFormat];
        $second = $scores[$formats[1]];
        $ambiguous = $best['confidence'] >= 80 && $best['confidence'] === $second['confidence'];
        $detectedFormat = $best['confidence'] >= 80 && ! $ambiguous
            ? OwnRevenueImportFormat::from($bestFormat)
            : null;
        $evidence = array_map(
            fn (string $header): string => "Encabezado: {$header}",
            $best['matches'],
        );

        if ($ambiguous) {
            $evidence[] = 'El libro contiene más de una firma fuerte de formato.';
        }

        return new WorkbookDetection(
            format: $detectedFormat,
            confidence: $best['confidence'],
            detectedYear: $this->detectedYear($workbook),
            evidence: $evidence,
        );
    }

    /** @return array<string, list<string>> */
    private function signatures(): array
    {
        $months = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre', 'anual',
        ];

        return [
            OwnRevenueImportFormat::Abpre->value => [
                'clave unidad responsable', 'partida', ...$months,
            ],
            OwnRevenueImportFormat::WorkSheet->value => [
                'actividad', 'concepto', 'partida', 'region', ...$months,
            ],
            OwnRevenueImportFormat::TechnicalSheet->value => [
                'partida', 'cantidad', 'unidad', 'descripcion', 'ragion', 'costo', 'mes presupuestado',
            ],
            OwnRevenueImportFormat::Fuel->value => [
                'fechas de la comision', 'modelo de vehiculo', 'recorrido', 'importe',
            ],
            OwnRevenueImportFormat::TravelExpenses->value => [
                'fechas de la comision', 'nombre de personal comisionado', 'costo uma', 'viaticos', 'hospedaje',
            ],
        ];
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
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function canonicalHeader(string $value): string
    {
        return match ($normalized = $this->normalize($value)) {
            'actividades unidad de presupuestacion' => 'actividad',
            'insumos' => 'concepto',
            default => $normalized,
        };
    }
}
