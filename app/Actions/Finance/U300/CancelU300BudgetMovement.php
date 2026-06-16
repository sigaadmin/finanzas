<?php

namespace App\Actions\Finance\U300;

use App\Models\Finance\U300\U300BudgetMovement;
use App\Models\Finance\U300\U300Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelU300BudgetMovement
{
    public function handle(
        U300Program $program,
        U300BudgetMovement $movement,
        User $cancelledBy,
        string $reason,
    ): U300BudgetMovement {
        return DB::transaction(function () use ($program, $movement, $cancelledBy, $reason): U300BudgetMovement {
            $movement = U300BudgetMovement::query()
                ->whereKey($movement->id)
                ->whereHas(
                    'budgetLine.budgetVersion',
                    fn ($query) => $query
                        ->where('u300_program_id', $program->id)
                        ->where('kind', 'adjusted')
                )
                ->lockForUpdate()
                ->first();

            if (! $movement) {
                throw ValidationException::withMessages([
                    'movement' => 'El movimiento no pertenece al proyecto adecuado.',
                ]);
            }

            if ($movement->cancelled_at !== null) {
                throw ValidationException::withMessages([
                    'movement' => 'El movimiento ya fue cancelado.',
                ]);
            }

            $movement->update([
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy->id,
                'cancellation_reason' => $reason,
            ]);

            return $movement->refresh();
        });
    }
}
