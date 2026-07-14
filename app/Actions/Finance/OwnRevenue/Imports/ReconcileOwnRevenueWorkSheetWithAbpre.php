<?php

namespace App\Actions\Finance\OwnRevenue\Imports;

use App\Data\Finance\OwnRevenue\Imports\ImportIssueData;
use App\Data\Finance\OwnRevenue\Imports\WorkSheetAnalysis;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFileStatus;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportFormat;
use App\Enums\Finance\OwnRevenue\Imports\OwnRevenueImportIssueSeverity;
use App\Models\Finance\OwnRevenue\Imports\OwnRevenueImportFile;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;

class ReconcileOwnRevenueWorkSheetWithAbpre
{
    /** @return list<ImportIssueData> */
    public function handle(OwnRevenueBudget $budget, WorkSheetAnalysis $analysis): array
    {
        $abpre = OwnRevenueImportFile::query()
            ->whereBelongsTo($budget, 'budget')
            ->where('format', OwnRevenueImportFormat::Abpre)
            ->where('status', OwnRevenueImportFileStatus::Confirmed)
            ->latest('confirmed_at')
            ->latest('id')
            ->first();

        if ($abpre === null || ! $abpre->abpreLines()->exists()) {
            return [new ImportIssueData(
                OwnRevenueImportIssueSeverity::Error,
                'work_sheet.abpre_required',
                null,
                'Confirme el ABPRE del presupuesto y vuelva a analizar la Hoja de trabajo.',
                ['requires_reanalysis' => true],
            )];
        }

        $workSheetTotals = [];
        $workSheetSourceRows = [];
        foreach ($analysis->lines as $line) {
            $code = $line->specificItemCode;
            $workSheetTotals[$code] = $this->add($workSheetTotals[$code] ?? '0', $line->annualAmountCents);
            $workSheetSourceRows[$code] = array_values(array_unique([
                ...($workSheetSourceRows[$code] ?? []),
                ...$line->sourceRows,
            ]));
            sort($workSheetSourceRows[$code]);
        }

        $abpreTotals = [];
        $abpreLineIds = [];
        foreach ($abpre->abpreLines()->get(['id', 'specific_item_code', 'annual_amount_cents']) as $line) {
            $code = $line->specific_item_code;
            $abpreTotals[$code] = $this->add($abpreTotals[$code] ?? '0', (string) $line->annual_amount_cents);
            $abpreLineIds[$code][] = $line->id;
        }

        $codes = array_values(array_unique([...array_keys($workSheetTotals), ...array_keys($abpreTotals)]));
        sort($codes, SORT_STRING);
        $issues = [];

        foreach ($codes as $code) {
            $workSheetTotal = $workSheetTotals[$code] ?? '0';
            $abpreTotal = $abpreTotals[$code] ?? '0';
            if ($this->compare($workSheetTotal, $abpreTotal) === 0) {
                continue;
            }

            $issues[] = new ImportIssueData(
                OwnRevenueImportIssueSeverity::Warning,
                'work_sheet.abpre_mismatch',
                $code,
                "La partida {$code} difiere del importe confirmado en el ABPRE.",
                [
                    'specific_item_code' => $code,
                    'work_sheet_total_cents' => $workSheetTotal,
                    'abpre_total_cents' => $abpreTotal,
                    'difference_cents' => $this->subtract($workSheetTotal, $abpreTotal),
                    'abpre_import_file_id' => $abpre->id,
                    'work_sheet_source_rows' => $workSheetSourceRows[$code] ?? [],
                    'abpre_line_ids' => $abpreLineIds[$code] ?? [],
                    'requires_decision' => true,
                ],
            );
        }

        return $issues;
    }

    private function add(string $left, string $right): string
    {
        $left = strrev($this->normalize($left));
        $right = strrev($this->normalize($right));
        $carry = 0;
        $result = '';

        for ($index = 0, $length = max(strlen($left), strlen($right)); $index < $length; $index++) {
            $total = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($total % 10);
            $carry = intdiv($total, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return $this->normalize(strrev($result));
    }

    private function subtract(string $left, string $right): string
    {
        $comparison = $this->compare($left, $right);
        if ($comparison === 0) {
            return '0';
        }

        $negative = $comparison < 0;
        $larger = strrev($this->normalize($negative ? $right : $left));
        $smaller = strrev($this->normalize($negative ? $left : $right));
        $borrow = 0;
        $result = '';

        for ($index = 0, $length = strlen($larger); $index < $length; $index++) {
            $digit = (int) $larger[$index] - $borrow - (int) ($smaller[$index] ?? 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result .= (string) $digit;
        }

        $result = $this->normalize(strrev($result));

        return $negative ? '-'.$result : $result;
    }

    private function compare(string $left, string $right): int
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    private function normalize(string $value): string
    {
        return ltrim($value, '0') ?: '0';
    }
}
