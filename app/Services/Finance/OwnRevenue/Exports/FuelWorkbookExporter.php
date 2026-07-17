<?php

namespace App\Services\Finance\OwnRevenue\Exports;

class FuelWorkbookExporter
{
    public function __construct(private readonly SingleSheetWorkbookWriter $writer) {}

    /** @param array<string, mixed> $snapshot */
    public function export(array $snapshot): string
    {
        return $this->writer->write('COMBUSTIBLE', $this->rows($snapshot));
    }

    /** @param array<string, mixed> $snapshot @return list<array<string, int|float|string>> */
    private function rows(array $snapshot): array
    {
        return collect($snapshot['fuel_needs'] ?? [])->map(fn (array $row): array => [
            'Actividad' => $row['activity'],
            'Fechas de la comisión' => $row['commission_date_label'] ?? '',
            'Mes operativo' => (int) ($row['operational_month'] ?? 0),
            'Mes presupuestal' => 4,
            'Motivo' => $row['reason'] ?? '',
            'Modelo del vehículo' => $row['vehicle_model'] ?? '',
            'Rendimiento (km/l)' => (float) ($row['kilometers_per_liter'] ?? 0),
            'Origen ida' => $row['outbound_origin'] ?? '',
            'Destino ida' => $row['outbound_destination'] ?? '',
            'Kilómetros ida' => (float) ($row['outbound_kilometers'] ?? 0),
            'Origen regreso' => $row['return_origin'] ?? '',
            'Destino regreso' => $row['return_destination'] ?? '',
            'Kilómetros regreso' => (float) ($row['return_kilometers'] ?? 0),
            'Kilómetros totales' => (float) ($row['total_kilometers'] ?? 0),
            'Litros' => (float) ($row['liters'] ?? 0),
            'Precio por litro' => (float) ($row['fuel_price'] ?? 0),
            'Cálculo' => ((int) ($row['mathematical_amount_cents'] ?? 0)) / 100,
            'Importe redondeado' => ((int) ($row['rounded_amount_cents'] ?? 0)) / 100,
            'Importe presupuestado' => ((int) $row['amount_cents']) / 100,
        ])->all();
    }
}
