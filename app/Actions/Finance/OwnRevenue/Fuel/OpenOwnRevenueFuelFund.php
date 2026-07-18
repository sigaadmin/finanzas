<?php

namespace App\Actions\Finance\OwnRevenue\Fuel;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\Fuel\OwnRevenueFuelFund;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpenOwnRevenueFuelFund
{
    public function handle(
        OwnRevenueExpenseDossier $sourceDossier,
        User $user,
        int $acquiredAmountCents,
    ): OwnRevenueFuelFund {
        Gate::forUser($user)->authorize('openFuelFund', $sourceDossier->budget);
        if ($acquiredAmountCents < 1) {
            throw ValidationException::withMessages(['acquired_amount_cents' => 'El valor adquirido debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($sourceDossier, $user, $acquiredAmountCents): OwnRevenueFuelFund {
            $budget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($sourceDossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('openFuelFund', $budget);
            if ($budget->fuelFund()->exists()) {
                throw ValidationException::withMessages(['fund' => 'El fondo operativo de combustible ya fue abierto.']);
            }
            $dossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($budget, 'budget')
                ->whereKey($sourceDossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            $line = OwnRevenueModifiedBudgetLine::query()
                ->whereBelongsTo($budget, 'budget')
                ->whereKey($dossier->own_revenue_modified_budget_line_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($dossier->status !== OwnRevenueExpenseDossierStatus::Paid) {
                throw ValidationException::withMessages(['source_expense_dossier_id' => 'El expediente de combustible debe estar pagado.']);
            }
            if ($line->specific_item_code !== '26101' || $line->month !== $budget->fuel_budget_month) {
                throw ValidationException::withMessages([
                    'source_expense_dossier_id' => 'Selecciona el expediente pagado de combustible correspondiente a abril.',
                ]);
            }

            return OwnRevenueFuelFund::query()->create([
                'own_revenue_budget_id' => $budget->id,
                'source_expense_dossier_id' => $dossier->id,
                'acquired_amount_cents' => $acquiredAmountCents,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);
        }, attempts: 3);
    }
}
