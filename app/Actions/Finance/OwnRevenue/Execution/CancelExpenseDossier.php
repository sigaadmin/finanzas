<?php

namespace App\Actions\Finance\OwnRevenue\Execution;

use App\Enums\Finance\OwnRevenue\OwnRevenueExpenseDossierStatus;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueExpenseDossier;
use App\Models\Finance\OwnRevenue\Execution\OwnRevenueModifiedBudgetLine;
use App\Models\Finance\OwnRevenue\OwnRevenueBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelExpenseDossier
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reason): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('cancelExpenseDossier', $dossier->budget);

        return DB::transaction(function () use ($dossier, $user, $reason): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('cancelExpenseDossier', $lockedBudget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($lockedDossier->status, [
                OwnRevenueExpenseDossierStatus::Draft,
                OwnRevenueExpenseDossierStatus::SufficiencyRequested,
                OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
                OwnRevenueExpenseDossierStatus::PurchaseInProgress,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'El expediente ya no puede cancelarse en su etapa actual.',
                ]);
            }
            OwnRevenueModifiedBudgetLine::query()
                ->whereKey($lockedDossier->own_revenue_modified_budget_line_id)
                ->lockForUpdate()
                ->firstOrFail();
            $fromStatus = $lockedDossier->status;
            $lockedDossier->update(['status' => OwnRevenueExpenseDossierStatus::Cancelled]);
            $lockedDossier->transitions()->create([
                'from_status' => $fromStatus,
                'to_status' => OwnRevenueExpenseDossierStatus::Cancelled,
                'reason' => $reason,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
