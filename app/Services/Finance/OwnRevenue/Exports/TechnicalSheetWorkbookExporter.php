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
            'Actividad' => $row['activity'],
            'Nombre de la actividad' => $row['activity_name'] ?? '',
            'Partida' => $row['item'],
            'Nombre de la partida' => $row['item_name'] ?? '',
            'Descripción' => $row['description'] ?? '',
            'Cantidad' => (float) ($row['quantity'] ?? 0),
            'Unidad' => $row['unit'] ?? '',
            'Precio unitario' => ((int) ($row['unit_price_cents'] ?? 0)) / 100,
            'Importe' => ((int) $row['amount_cents']) / 100,
            'Mes' => (int) $row['month'],
            'Impacto en metas / justificación' => $row['impact_on_goals'] ?? '',
            'Región' => '02-001',
            'Nombre de la región' => 'Felipe Carrillo Puerto',
        ])->all();
    }
}
