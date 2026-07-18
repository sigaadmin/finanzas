<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenuePurchaseResponsibility;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueExpenseRequirements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateOwnRevenueExpenseDossier
{
    public function __construct(private readonly OwnRevenueExpenseRequirements $requirements) {}

    /**
     * @param  array{concept: string, amount_cents: int, purchase_responsibility: string, external_reference?: ?string, notes?: ?string}  $data
     */
    public function handle(
        OwnRevenueBudget $budget,
        OwnRevenueModifiedBudgetLine $line,
        User $user,
        array $data,
    ): OwnRevenueExpenseDossier {
        Gate::forUser($user)->authorize('createExpenseDossier', $budget);

        return DB::transaction(function () use ($budget, $line, $user, $data): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($budget->id);
            Gate::forUser($user)->authorize('createExpenseDossier', $lockedBudget);
            $lockedLine = OwnRevenueModifiedBudgetLine::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($line->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedLine instanceof OwnRevenueModifiedBudgetLine) {
                throw ValidationException::withMessages([
                    'own_revenue_modified_budget_line_id' => 'La partida no pertenece a este presupuesto.',
                ]);
            }

            $concept = trim($data['concept']);
            if ($concept === '') {
                throw ValidationException::withMessages(['concept' => 'Describe el gasto que se realizará.']);
            }
            if ($data['amount_cents'] < 1) {
                throw ValidationException::withMessages(['amount_cents' => 'El importe debe ser mayor que cero.']);
            }
            $responsibility = OwnRevenuePurchaseResponsibility::tryFrom($data['purchase_responsibility']);
            if ($responsibility === null) {
                throw ValidationException::withMessages([
                    'purchase_responsibility' => 'Selecciona quién realizará la compra.',
                ]);
            }

            $sequenceNumber = ((int) $lockedBudget->expenseDossiers()->max('sequence_number')) + 1;
            $dossier = OwnRevenueExpenseDossier::query()->create([
                'own_revenue_budget_id' => $lockedBudget->id,
                'own_revenue_modified_budget_line_id' => $lockedLine->id,
                'sequence_number' => $sequenceNumber,
                'folio' => sprintf('IP-%d-%04d', $lockedBudget->fiscal_year, $sequenceNumber),
                'status' => OwnRevenueExpenseDossierStatus::Draft,
                'concept' => $concept,
                'amount_cents' => $data['amount_cents'],
                'purchase_responsibility' => $responsibility,
                'external_reference' => $this->nullableTrimmed($data['external_reference'] ?? null),
                'notes' => $this->nullableTrimmed($data['notes'] ?? null),
                'requested_by' => $user->id,
            ]);
            $dossier->transitions()->create([
                'from_status' => null,
                'to_status' => OwnRevenueExpenseDossierStatus::Draft,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);
            $this->requirements->syncAllStages($dossier);

            return $dossier;
        }, attempts: 3);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
