<?php

namespace App\Services\Finance\OwnRevenue\Exports;

class TravelExpensesWorkbookExporter
{
    public function __construct(private readonly SingleSheetWorkbookWriter $writer) {}

    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        return $this->writer->write('VIÁTICOS', $this->rows($snapshot));
    }

    /** @param array<string, mixed> $snapshot @return list<array<string, int|float|string>> */
    private function rows(array $snapshot): array
    {
        return collect($snapshot['travel_commissions'] ?? [])->map(fn (array $row): array => [
            'Actividad' => $row['activity'], 'Mes' => $row['month'], 'Viáticos' => ((int) $row['participants_amount_cents']) / 100, 'Vuelos' => ((int) $row['flight_amount_cents']) / 100,
        ])->all();
    }
}
