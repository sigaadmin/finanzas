<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConfirmExpenseSufficiency
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('confirmExpenseSufficiency', $dossier->budget);

        return DB::transaction(function () use ($dossier, $user): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('confirmExpenseSufficiency', $lockedBudget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedDossier->status !== OwnRevenueExpenseDossierStatus::SufficiencyRequested) {
                throw ValidationException::withMessages([
                    'status' => 'La suficiencia debe estar solicitada antes de confirmarla.',
                ]);
            }

            $lockedDossier->update([
                'status' => OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
                'sufficiency_confirmed_by' => $user->id,
                'sufficiency_confirmed_at' => now(),
            ]);
            $lockedDossier->transitions()->create([
                'from_status' => OwnRevenueExpenseDossierStatus::SufficiencyRequested,
                'to_status' => OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
