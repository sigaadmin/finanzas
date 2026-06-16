<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300BudgetLine;
use App\Models\Finance\U300\U300BudgetMovement;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreU300BudgetMovement
{
    /**
     * @param  array{u300_budget_line_id: int, type: string, movement_date: string, concept: string, document_reference: string|null, amount_cents: int}  $data
     */
    public function handle(U300Program $program, User $recordedBy, array $data): U300BudgetMovement
    {
        return DB::transaction(function () use ($program, $recordedBy, $data): U300BudgetMovement {
            $line = U300BudgetLine::query()
                ->whereKey($data['u300_budget_line_id'])
                ->whereHas(
                    'budgetVersion',
                    fn ($query) => $query
                        ->where('u300_program_id', $program->id)
                        ->where('kind', 'adjusted')
                )
                ->lockForUpdate()
                ->first();

            if (! $line) {
                throw ValidationException::withMessages([
                    'u300_budget_line_id' => 'La partida no pertenece al proyecto adecuado.',
                ]);
            }

            if ($line->expense_classification_id === null) {
                throw ValidationException::withMessages([
                    'u300_budget_line_id' => 'La partida debe tener una clasificación COG antes de registrar movimientos.',
                ]);
            }

            if (! $this->matchesExerciseMonth($line->exercise_month, $data['movement_date'])) {
                throw ValidationException::withMessages([
                    'movement_date' => 'La fecha del movimiento no coincide con el mes autorizado para ejercer la partida.',
                ]);
            }

            $executedCents = $this->executedCents($line);
            $availableCents = $line->amount_cents - $executedCents;

            if (in_array($data['type'], ['commitment', 'expense'], true) && $data['amount_cents'] > $availableCents) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'El movimiento excede el monto disponible de la partida.',
                ]);
            }

            if ($data['type'] === 'reimbursement' && $data['amount_cents'] > $executedCents) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'El reintegro excede el monto ejercido de la partida.',
                ]);
            }

            return U300BudgetMovement::create([
                ...$data,
                'recorded_by' => $recordedBy->id,
            ]);
        });
    }

    private function executedCents(U300BudgetLine $line): int
    {
        $movements = $line->movements()
            ->select(['type', 'amount_cents'])
            ->whereNull('cancelled_at')
            ->get();

        return (int) $movements->sum(
            fn (U300BudgetMovement $movement): int => $movement->type === 'reimbursement'
                ? -$movement->amount_cents
                : $movement->amount_cents
        );
    }

    private function matchesExerciseMonth(?string $exerciseMonth, string $movementDate): bool
    {
        if ($exerciseMonth === null) {
            return true;
        }

        $months = [
            'ENE' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'ABR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AGO' => 8,
            'SEP' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DIC' => 12,
        ];

        $expectedMonth = $months[mb_strtoupper($exerciseMonth)] ?? null;

        if ($expectedMonth === null) {
            return true;
        }

        return CarbonImmutable::parse($movementDate)->month === $expectedMonth;
    }
}
