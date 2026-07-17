<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueBudgetStatus;
use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use App\Services\Finance\OwnRevenue\Execution\OwnRevenueBudgetBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RequestExpenseSufficiency
{
    public function __construct(private readonly OwnRevenueBudgetBalance $balances) {}

    public function handle(OwnRevenueExpenseDossier $dossier, User $user): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('requestExpenseSufficiency', $dossier->budget);

        return DB::transaction(function () use ($dossier, $user): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('requestExpenseSufficiency', $lockedBudget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'Solo un expediente en borrador puede solicitar suficiencia.',
                ]);
            }
            $line = OwnRevenueModifiedBudgetLine::query()
                ->whereKey($lockedDossier->own_revenue_modified_budget_line_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedDossier->amount_cents > $this->balances->availableCents($line)) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'La partida no tiene saldo disponible suficiente para reservar este gasto.',
                ]);
            }

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
                'sufficiency_requested_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::Draft,
                'to_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);
            if ($lockedBudget->status === OwnRevenueBudgetStatus::InitialAuthorized) {
                $lockedBudget->update(['status' => OwnRevenueBudgetStatus::InExecution]);
            }

            return $lockedDossier;
        }, attempts: 3);
    }
}
