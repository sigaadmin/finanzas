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

class RejectExpenseDossier
{
    public function handle(OwnRevenueExpenseDossier $dossier, User $user, string $reason): OwnRevenueExpenseDossier
    {
        Gate::forUser($user)->authorize('rejectExpenseDossier', $dossier->budget);

        return DB::transaction(function () use ($dossier, $user, $reason): OwnRevenueExpenseDossier {
            $lockedBudget = OwnRevenueBudget::query()->lockForUpdate()->findOrFail($dossier->own_revenue_budget_id);
            Gate::forUser($user)->authorize('rejectExpenseDossier', $lockedBudget);
            $lockedDossier = OwnRevenueExpenseDossier::query()
                ->whereBelongsTo($lockedBudget, 'budget')
                ->whereKey($dossier->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($lockedDossier->status, [
                OwnRevenueExpenseDossierStatus::SufficiencyRequested,
                OwnRevenueExpenseDossierStatus::SufficiencyConfirmed,
                OwnRevenueExpenseDossierStatus::PurchaseInProgress,
                OwnRevenueExpenseDossierStatus::PaymentRequested,
                OwnRevenueExpenseDossierStatus::FinanceAuthorized,
                OwnRevenueExpenseDossierStatus::BudgetOfficeAuthorized,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'El expediente no puede rechazarse en su etapa actual.',
                ]);
            }
            OwnRevenueModifiedBudgetLine::query()
                ->whereKey($lockedDossier->own_revenue_modified_budget_line_id)
                ->lockForUpdate()
                ->firstOrFail();
            $fromStatus = $lockedDossier->status;
            $lockedDossier->update(['status' => OwnRevenueExpenseDossierStatus::Rejected]);
            $lockedDossier->transitions()->create([
                'from_status' => $fromStatus,
                'to_status' => OwnRevenueExpenseDossierStatus::Rejected,
                'reason' => $reason,
                'actor_id' => $user->id,
                'occurred_at' => now(),
            ]);

            return $lockedDossier;
        }, attempts: 3);
    }
}
