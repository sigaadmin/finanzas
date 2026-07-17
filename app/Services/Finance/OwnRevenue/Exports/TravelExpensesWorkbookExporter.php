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
        return collect($snapshot['travel_commissions'] ?? [])->flatMap(function (array $commission): array {
            return collect($commission['participants'] ?? [])->values()->map(function (array $participant, int $index) use ($commission): array {
                $flight = $index === 0 ? ((int) ($commission['flight_amount_cents'] ?? 0)) / 100 : 0;
                $participantTotal = ((int) ($participant['amount_cents'] ?? 0)) / 100;

                return [
                    'Actividad' => $commission['activity'],
                    'Fechas de la comisión' => $commission['commission_date_label'] ?? '',
                    'Mes presupuestal' => (int) $commission['month'],
                    'Motivo' => $commission['reason'] ?? '',
                    'Destino' => $commission['destination'] ?? '',
                    'Participante' => $participant['person_name'] ?? '',
                    'Cargo' => $participant['position'] ?? '',
                    'Días' => (float) ($participant['commission_days'] ?? 0),
                    'Zona alimentación' => $commission['food_zone'] ?? '',
                    'Zona hospedaje' => $commission['lodging_zone'] ?? '',
                    'UMA' => (float) ($commission['uma_value'] ?? 0),
                    'UMA alimentación' => (float) ($participant['per_diem_uma'] ?? 0),
                    'UMA hospedaje' => (float) ($participant['lodging_uma'] ?? 0),
                    'Alimentación' => ((int) ($participant['per_diem_amount_cents'] ?? 0)) / 100,
                    'Hospedaje' => ((int) ($participant['lodging_amount_cents'] ?? 0)) / 100,
                    'Vuelo' => $flight,
                    'Total' => $participantTotal + $flight,
                    'Región' => '02-001',
                    'Nombre de la región' => 'Felipe Carrillo Puerto',
                ];
            })->all();
        })->all();
    }
}
