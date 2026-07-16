<?php

namespace App\Services\Finance\OwnRevenue\Exports;

class TechnicalSheetWorkbookExporter
{
    public function __construct(private readonly SingleSheetWorkbookWriter $writer) {}

    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        return $this->writer->write('FICHA TÉCNICA', $this->rows($snapshot));
    }

    /** @param array<string, mixed> $snapshot @return list<array<string, int|float|string>> */
    private function rows(array $snapshot): array
    {
        return collect($snapshot['technical_needs'] ?? [])->map(fn (array $row): array => [
            'Actividad' => $row['activity'], 'Partida' => $row['item'], 'Mes' => $row['month'], 'Importe' => ((int) $row['amount_cents']) / 100,
        ])->all();
    }
}
