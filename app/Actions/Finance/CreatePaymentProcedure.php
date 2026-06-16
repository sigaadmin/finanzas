<?php

namespace App\Actions\Finance;

use App\Enums\Finance\ChargeConceptType;
use App\Enums\Finance\PaymentProcedureStatus;
use App\Models\ChargeConcept;
use App\Models\PaymentProcedure;
use App\Models\StudentSnapshot;
use App\Models\User;
use App\Services\Finance\FolioService;
use Illuminate\Support\Facades\DB;

class CreatePaymentProcedure
{
    public function __construct(private readonly FolioService $folios) {}

    /**
     * @param  array<string, mixed>  $student
     * @param  list<array{charge_concept_id: int, quantity: int}>  $items
     */
    public function handle(User $createdBy, array $student, array $items): PaymentProcedure
    {
        return DB::transaction(function () use ($createdBy, $student, $items): PaymentProcedure {
            $studentSnapshot = StudentSnapshot::create($student);
            $items = collect($items)
                ->groupBy('charge_concept_id')
                ->map(fn ($group, int|string $conceptId): array => [
                    'charge_concept_id' => (int) $conceptId,
                    'quantity' => $group->sum('quantity'),
                ])
                ->values();

            $concepts = ChargeConcept::query()
                ->whereIn('id', $items->pluck('charge_concept_id'))
                ->get()
                ->keyBy('id');

            $preparedItems = $items->map(function (array $item) use ($concepts): array {
                /** @var ChargeConcept $concept */
                $concept = $concepts->get($item['charge_concept_id']);
                $quantity = $concept->type === ChargeConceptType::Internal && $concept->allows_quantity
                    ? max(1, (int) $item['quantity'])
                    : 1;

                return [
                    'concept' => $concept,
                    'quantity' => $quantity,
                    'subtotal_pesos' => $concept->amount_pesos * $quantity,
                ];
            });

            $totalPesos = $preparedItems->sum('subtotal_pesos');

            $procedure = PaymentProcedure::create([
                'folio' => $this->folios->procedureFolio(),
                'student_snapshot_id' => $studentSnapshot->id,
                'created_by' => $createdBy->id,
                'status' => PaymentProcedureStatus::PendingPayment,
                'total_pesos' => $totalPesos,
            ]);

            foreach ($preparedItems as $item) {
                /** @var ChargeConcept $concept */
                $concept = $item['concept'];

                $procedure->items()->create([
                    'charge_concept_id' => $concept->id,
                    'concept_name' => $concept->name,
                    'concept_type' => $concept->type,
                    'unit_amount_pesos' => $concept->amount_pesos,
                    'quantity' => $item['quantity'],
                    'subtotal_pesos' => $item['subtotal_pesos'],
                ]);
            }

            return $procedure->load(['studentSnapshot', 'items']);
        });
    }
}
