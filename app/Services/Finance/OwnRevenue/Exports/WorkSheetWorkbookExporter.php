<?php

namespace App\Services\Finance\OwnRevenue\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WorkSheetWorkbookExporter
{
    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('HOJA FINAL');
        $sheet->fromArray([['CENTRO REGIONAL DE EDUCACIÓN NORMAL / FELIPE CARRILLO PUERTO, QUINTANA ROO'], [], [
            'Actividad', 'Insumos', 'Partida', 'Región', 'Nombre región', 'Presupuesto', 'Calendario',
        ], ['', '', '', '', '', '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Anual']]);
        foreach ($this->consolidatedGroups($snapshot['reconciliation']['groups'] ?? []) as $index => $group) {
            $row = $index + 5;
            $itemName = $snapshot['expense_classifications'][$group['specific_item_code']]['specific_item_name']
                ?? $group['specific_item_name'];
            $sheet->fromArray([[
                $this->activityLabel($group['activity_code'], $group['activity_name']), $itemName,
                (int) $group['specific_item_code'], '02-001', 'FELIPE CARRILLO PUERTO', $group['annual_amount_cents'] / 100,
                ...array_map(fn (int $amountCents): ?float => $amountCents === 0 ? null : $amountCents / 100, $group['months']),
                $group['annual_amount_cents'] / 100,
            ]], null, 'A'.$row);
        }
        $sheet->freezePane('A5');
        $path = tempnam(sys_get_temp_dir(), 'own-revenue-work-sheet');
        (new Xlsx($book))->save($path);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: throw new \RuntimeException('No fue posible generar la Hoja de trabajo.');
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array{activity_code: string, activity_name: string, specific_item_code: string, specific_item_name: string|null, months: list<int>, annual_amount_cents: int}>
     */
    private function consolidatedGroups(array $groups): array
    {
        $consolidated = [];
        foreach ($groups as $group) {
            $activityCode = (string) ($group['activity_code'] ?? '');
            $specificItemCode = (string) ($group['specific_item_code'] ?? '');
            if ($activityCode === '' || $specificItemCode === '') {
                continue;
            }
            $key = $activityCode.'|'.$specificItemCode;
            $consolidated[$key] ??= [
                'activity_code' => $activityCode,
                'activity_name' => (string) ($group['activity_name'] ?? ''),
                'specific_item_code' => $specificItemCode,
                'specific_item_name' => $group['specific_item_name'] ?? null,
                'months' => array_fill(0, 12, 0),
                'annual_amount_cents' => 0,
            ];
            $month = (int) ($group['month'] ?? 0);
            $amountCents = (int) ($group['target_amount_cents'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $consolidated[$key]['months'][$month - 1] += $amountCents;
            }
            $consolidated[$key]['annual_amount_cents'] += $amountCents;
        }
        uasort($consolidated, fn (array $left, array $right): int => [
            $left['activity_code'], (int) $left['specific_item_code'],
        ] <=> [
            $right['activity_code'], (int) $right['specific_item_code'],
        ]);

        return array_values($consolidated);
    }

    private function activityLabel(string $activityCode, string $activityName): string
    {
        return $activityName === '' ? $activityCode : $activityCode.' - '.$activityName;
    }
}
