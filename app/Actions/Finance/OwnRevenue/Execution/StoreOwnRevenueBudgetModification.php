<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetModificationType;
use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Models\Finance\ExpenseClassification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueBudgetModification;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueBudgetBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreOwnRevenueBudgetModification
{
    public function __construct(private readonly OwnRevenueBudgetBalance $balances) {}

    /**
     * @param  array{type: string, source_line_id: int, destination_expense_classification_id: int, destination_month: int, amount_cents: int, reason: string}  $data
     */
    public function handle(OwnRevenueBudget $budget, User $user, array $data): OwnRevenueBudgetModification
    {
        Gate::forUser($user)->authorize('manageExecution', $budget);

        return DB::transaction(function () use ($budget, $user, $data): OwnRevenueBudgetModification {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('manageExecution', $lockedBudget);
            $type = OwnRevenueBudgetModificationType::tryFrom($data['type']);
            if ($type === null) {
                throw ValidationException::withMessages(['type' => 'Selecciona un tipo de modificación válido.']);
            }
            if ($data['amount_cents'] < 1) {
                throw ValidationException::withMessages(['amount_cents' => 'El importe debe ser mayor que cero.']);
            }
            if (trim($data['reason']) === '') {
                throw ValidationException::withMessages(['reason' => 'Explica el motivo de la modificación.']);
            }

            $source = OwnRevenueModifiedBudgetLine::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($data['source_line_id'])
                ->lockForUpdate()
                ->first();
            if (! $source instanceof OwnRevenueModifiedBudgetLine) {
                throw ValidationException::withMessages(['source_line_id' => 'La partida de origen no pertenece a este presupuesto.']);
            }
            $classification = ExpenseClassification::query()
                ->whereKey($data['destination_expense_classification_id'])
                ->where('fiscal_year', $lockedBudget->fiscal_year)
                ->first();
            if (! $classification instanceof ExpenseClassification) {
                throw ValidationException::withMessages([
                    'destination_expense_classification_id' => 'La partida de destino no pertenece al COG del ejercicio.',
                ]);
            }

            $this->validateDestination($type, $source, $classification, $data['destination_month']);
            $destination = OwnRevenueModifiedBudgetLine::query()->firstOrCreate(
                [
                    'own_revenue_budget_id' => $lockedBudget->id,
                    'specific_item_code' => $classification->specific_item_code,
                    'month' => $data['destination_month'],
                ],
                [
                    'own_revenue_initial_budget_id' => $source->own_revenue_initial_budget_id,
                    'expense_classification_id' => $classification->id,
                    'chapter_code' => $classification->chapter_code,
                    'chapter_name' => $classification->chapter_name,
                    'specific_item_name' => $classification->specific_item_name,
                    'initial_amount_cents' => 0,
                ],
            );
            OwnRevenueModifiedBudgetLine::query()
                ->whereKey([$source->id, $destination->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $source->refresh();
            $destination->refresh();
            $sourceBefore = $this->balances->availableCents($source);
            if ($data['amount_cents'] > $sourceBefore) {
                throw ValidationException::withMessages(['amount_cents' => 'La modificación excede el saldo disponible de la partida de origen.']);
            }
            $destinationBefore = $this->balances->availableCents($destination);

            $movement = OwnRevenueBudgetModification::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'type' => $type,
                'source_line_id' => $source->id,
                'destination_line_id' => $destination->id,
                'amount_cents' => $data['amount_cents'],
                'reason' => trim($data['reason']),
                'source_balance_before_cents' => $sourceBefore,
                'source_balance_after_cents' => $sourceBefore - $data['amount_cents'],
                'destination_balance_before_cents' => $destinationBefore,
                'destination_balance_after_cents' => $destinationBefore + $data['amount_cents'],
                'recorded_by' => $user->id,
                'recorded_at' => now(),
            ]);
            if ($lockedBudget->status === OwnRevenueBudgetStatus::InitialAuthorized) {
                $lockedBudget->update(['status' => OwnRevenueBudgetStatus::InExecution]);
            }

            return $movement;
        }, attempts: 3);
    }

    private function validateDestination(
        OwnRevenueBudgetModificationType $type,
        OwnRevenueModifiedBudgetLine $source,
        ExpenseClassification $destination,
        int $destinationMonth,
    ): void {
        if ($destinationMonth < 1 || $destinationMonth > 12) {
            throw ValidationException::withMessages(['destination_month' => 'Selecciona un mes de destino válido.']);
        }
        if ($type === OwnRevenueBudgetModificationType::Transfer) {
            if ($destination->specific_item_code === $source->specific_item_code) {
                throw ValidationException::withMessages(['destination_expense_classification_id' => 'La transferencia debe dirigirse a una partida diferente.']);
            }
            if ($destination->chapter_code !== $source->chapter_code) {
                throw ValidationException::withMessages(['destination_expense_classification_id' => 'La transferencia debe permanecer dentro del mismo capítulo.']);
            }
            if ($destinationMonth !== $source->month) {
                throw ValidationException::withMessages(['destination_month' => 'La transferencia debe conservar el mismo mes.']);
            }

            return;
        }
        if ($destination->specific_item_code !== $source->specific_item_code) {
            throw ValidationException::withMessages(['destination_expense_classification_id' => 'La recalendarización debe conservar la misma partida.']);
        }
        if ($destinationMonth <= $source->month) {
            throw ValidationException::withMessages(['destination_month' => 'La recalendarización debe dirigirse a un mes futuro.']);
        }
    }
}
